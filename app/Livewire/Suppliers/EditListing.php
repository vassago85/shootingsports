<?php

namespace App\Livewire\Suppliers;

use App\Enums\ProviderCategory;
use App\Models\Provider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Owner (or staff) update for the listing they can change themselves.
 * Category, province, town, and review status stay with staff.
 */
class EditListing extends Component
{
    use WithFileUploads;

    public Provider $provider;

    #[Validate('required|string|max:160')]
    public string $name = '';

    /** @var list<string> */
    #[Validate('array')]
    public array $services = [];

    #[Validate('nullable|email|max:255')]
    public string $email = '';

    #[Validate('nullable|string|max:40')]
    public string $phone = '';

    #[Validate('nullable|url|max:255')]
    public string $website_url = '';

    #[Validate('nullable|image|mimes:jpeg,png,webp|max:3072')]
    public ?TemporaryUploadedFile $logo = null;

    #[Validate('nullable|string|max:160')]
    public string $tagline = '';

    #[Validate('required|string|min:20|max:2000')]
    public string $description = '';

    public function mount(Provider $provider): void
    {
        Gate::authorize('update', $provider);

        $this->provider = $provider;
        $this->name = $provider->name;
        $this->email = (string) $provider->email;
        $this->phone = (string) $provider->phone;
        $this->website_url = (string) $provider->website_url;
        $this->services = collect((array) $provider->services)
            ->filter(fn (mixed $value): bool => is_string($value) && $value !== '' && $value !== $provider->category?->value)
            ->values()
            ->all();
        $this->tagline = (string) $provider->tagline;
        $this->description = (string) $provider->description;
    }

    public function save(): void
    {
        Gate::authorize('update', $this->provider);

        $this->validate([
            'name' => 'required|string|max:160',
            'services' => 'array',
            'services.*' => ['string', 'in:'.implode(',', array_map(fn ($c) => $c->value, ProviderCategory::publicCases()))],
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:40',
            'website_url' => 'nullable|url|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,webp|max:3072',
            'tagline' => 'nullable|string|max:160',
            'description' => 'required|string|min:20|max:2000',
        ]);

        $category = $this->provider->category?->value;
        $services = collect($this->services)
            ->filter(fn (mixed $value): bool => is_string($value) && $value !== '' && $value !== $category)
            ->unique()
            ->values()
            ->all();

        $this->provider->name = trim($this->name);
        $this->provider->services = $services !== [] ? $services : null;
        $this->provider->email = filled($this->email) ? strtolower(trim($this->email)) : null;
        $this->provider->phone = filled($this->phone) ? trim($this->phone) : null;
        $this->provider->website_url = filled($this->website_url) ? trim($this->website_url) : null;
        $this->provider->tagline = filled($this->tagline) ? trim($this->tagline) : null;
        $this->provider->description = trim($this->description);

        if ($this->logo !== null && ! $this->provider->attachLogo($this->logo)) {
            $this->addError('logo', 'The logo could not be saved. Try a smaller PNG or JPEG.');

            return;
        }

        $this->provider->save();

        Session::flash('status', 'Your listing details are saved.');

        $this->redirect(route('suppliers.onboard.thanks', ['provider' => $this->provider->slug]), navigate: false);
    }

    public function render()
    {
        return view('livewire.suppliers.edit-listing', [
            'serviceOptions' => $this->serviceOptions(),
        ])->layout('components.layouts.public', ['title' => 'Your listing']);
    }

    /**
     * @return array<string, string>
     */
    private function serviceOptions(): array
    {
        $options = [];
        $primary = $this->provider->category?->value;

        foreach (ProviderCategory::publicCases() as $case) {
            if ($primary !== null && $case->value === $primary) {
                continue;
            }

            $options[$case->value] = $case->getLabel();
        }

        return $options;
    }
}
