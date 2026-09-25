<?php

namespace App\Livewire\Suppliers;

use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Enums\ProviderCategory;
use App\Enums\Province;
use App\Enums\VerificationState;
use App\Mail\SupplierListingSubmittedMail;
use App\Models\Provider;
use App\Models\User;
use App\Support\StaffInbox;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Supplier onboarding step 2 — create the industry listing once the
 * user has verified their email address. Route (see routes/web.php)
 * is gated by `auth` + `verified` middleware, so this component can
 * safely assume both.
 *
 * We create exactly one Provider row per user for the initial MVP.
 * If they already own a listing, we redirect them to its public page
 * with a flash. Additional services beyond the primary category are
 * stored as a JSON array on providers.services (see the migration).
 *
 * Every listing is created with:
 *   - status = Pending          (staff publish)
 *   - verification_state = Unconfirmed
 *   - source = Claimed          (self-registered, not staff-created)
 *   - claimed_by = auth()->id() (so ProviderPolicy::update returns true)
 */
class CreateListing extends Component
{
    use WithFileUploads;

    #[Validate('required|string|max:160')]
    public string $name = '';

    #[Validate('required|string')]
    public string $category = '';

    /** @var list<string> */
    #[Validate('array')]
    public array $services = [];

    #[Validate('required|string')]
    public string $province = '';

    #[Validate('required|string|max:120')]
    public string $town = '';

    #[Validate('nullable|email|max:255')]
    public ?string $email = null;

    #[Validate('nullable|string|max:40')]
    public ?string $phone = null;

    #[Validate('nullable|url|max:255')]
    public ?string $website_url = null;

    #[Validate('nullable|image|mimes:jpeg,png,webp|max:3072')]
    public ?TemporaryUploadedFile $logo = null;

    #[Validate('nullable|string|max:160')]
    public string $tagline = '';

    #[Validate('required|string|min:20|max:2000')]
    public string $description = '';

    public function mount(): void
    {
        $user = Auth::user();

        // Idempotent onboarding: if this user already has a listing,
        // the form is not the right screen — send them to the pending
        // confirmation page instead. Route param is the listing slug.
        $existing = $user?->providers()->first();

        if ($existing !== null) {
            $this->redirect(route('suppliers.onboard.thanks', ['provider' => $existing->slug]), navigate: false);

            return;
        }

        // Pre-fill from the signup step so the supplier does not
        // retype their business name. The email defaults to the
        // account address for the same reason.
        $pendingName = trim((string) ($user?->pending_business_name ?: Session::pull('supplier.pending_business_name', '')));
        Session::forget('supplier.pending_business_name');

        if ($pendingName !== '' && $this->name === '') {
            $this->name = $pendingName;
        }

        if ($this->email === null) {
            $this->email = $user?->email;
        }
    }

    /**
     * Options for the "Additional services" checklist. Excludes the
     * currently-selected primary category so a supplier cannot pick
     * "Dealer" as both primary and secondary.
     *
     * @return array<string, string>
     */
    public function getServiceOptionsProperty(): array
    {
        $options = [];

        foreach (ProviderCategory::publicCases() as $case) {
            if ($this->category !== '' && $case->value === $this->category) {
                continue;
            }

            $options[$case->value] = $case->getLabel();
        }

        return $options;
    }

    public function submit(): void
    {
        $this->validate([
            'name' => 'required|string|max:160',
            'category' => ['required', 'string', 'in:'.implode(',', array_map(fn ($c) => $c->value, ProviderCategory::publicCases()))],
            'services' => 'array',
            'services.*' => ['string', 'in:'.implode(',', array_map(fn ($c) => $c->value, ProviderCategory::publicCases()))],
            'province' => ['required', 'string', 'in:'.implode(',', array_map(fn ($p) => $p->value, Province::cases()))],
            'town' => 'required|string|max:120',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:40',
            'website_url' => 'nullable|url|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,webp|max:3072',
            'tagline' => 'nullable|string|max:160',
            'description' => 'required|string|min:20|max:2000',
        ]);

        $user = Auth::user();

        if ($user === null) {
            $this->redirect(route('login'), navigate: false);

            return;
        }

        $services = collect($this->services)
            ->filter(fn ($v): bool => is_string($v) && $v !== '' && $v !== $this->category)
            ->unique()
            ->values()
            ->all();

        $provider = DB::transaction(function () use ($user, $services): ?Provider {
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            $existing = Provider::query()->where('claimed_by', $user->id)->first();

            if ($existing !== null) {
                return $existing;
            }

            $provider = new Provider([
                'name' => trim($this->name),
                'category' => $this->category,
                'services' => $services !== [] ? $services : null,
                'province' => $this->province,
                'town' => trim($this->town),
                'email' => filled($this->email) ? strtolower(trim((string) $this->email)) : null,
                'phone' => filled($this->phone) ? trim((string) $this->phone) : null,
                'website_url' => filled($this->website_url) ? trim((string) $this->website_url) : null,
                'tagline' => filled($this->tagline) ? trim($this->tagline) : null,
                'description' => trim($this->description),
                'status' => ListingStatus::Pending,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Claimed,
                'claimed_by' => $user->id,
            ]);

            if ($this->logo !== null && ! $provider->attachLogo($this->logo)) {
                return null;
            }

            $provider->save();
            $user->forceFill(['pending_business_name' => null])->save();

            return $provider;
        });

        if ($provider === null) {
            $this->addError('logo', 'The logo could not be saved. Try a smaller PNG or JPEG.');

            return;
        }

        if ($provider->wasRecentlyCreated) {
            $provider->setRelation('claimedBy', $user);
            StaffInbox::queue(new SupplierListingSubmittedMail($provider), $user->email);
            Session::flash('status', 'Thanks. Your listing is in for review. Staff usually publish within one working day, and you will get an email as soon as it goes live.');
        }

        $this->redirect(route('suppliers.onboard.thanks', ['provider' => $provider->slug]), navigate: false);
    }

    public function render()
    {
        return view('livewire.suppliers.create-listing', [
            'categoryOptions' => collect(ProviderCategory::publicCases())
                ->mapWithKeys(fn (ProviderCategory $c): array => [$c->value => $c->getLabel()])
                ->all(),
            'provinceOptions' => collect(Province::cases())
                ->mapWithKeys(fn (Province $p): array => [$p->value => $p->getLabel()])
                ->all(),
            'serviceOptions' => $this->serviceOptions,
        ])->layout('components.layouts.public', ['title' => 'Register your business']);
    }
}
