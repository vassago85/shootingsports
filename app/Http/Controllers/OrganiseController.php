<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Enums\MembershipStatus;
use App\Enums\OrganisationKind;
use App\Enums\OrganisationType;
use App\Enums\OrganisationUserRole;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Paystack\PaystackClient;
use App\Services\Platform\PlatformInvoiceNumbers;
use App\Services\Platform\SingleEventPurchase;
use App\Support\PlatformFees;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Public one-shot "organise a single event" product: sign up, get a
 * single_event organisation + Draft Event + a VAT invoice, pay once via
 * Paystack. The Paystack webhook promotes the event out of Draft.
 */
class OrganiseController extends Controller
{
    public function __construct(
        private readonly PaystackClient $paystack,
        private readonly PlatformInvoiceNumbers $invoiceNumbers,
    ) {}

    public function show(): View
    {
        return view('organise.signup', $this->priceBreakdown());
    }

    public function submit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'event_name' => ['required', 'string', 'max:160'],
            'event_date' => ['required', 'date', 'after:today'],
            'venue_city' => ['required', 'string', 'max:120'],
        ]);

        ['excl' => $excl, 'vat' => $vat, 'incl' => $incl] = $this->priceBreakdown();

        [$user, $authorizationUrl] = DB::transaction(function () use ($validated, $excl, $vat, $incl): array {
            $user = User::query()->create([
                'name' => trim($validated['name']),
                'email' => strtolower(trim($validated['email'])),
                'password' => $validated['password'],
                'is_staff' => false,
                'is_match_director' => false,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            $organisation = Organisation::query()->create([
                'name' => trim($validated['event_name']),
                'type' => OrganisationType::Club,
                'kind' => OrganisationKind::SingleEvent,
                'status' => ListingStatus::Pending,
                'town' => trim($validated['venue_city']),
                'email' => $user->email,
                'source' => ListingSource::Submission,
                'claimed_by' => $user->id,
            ]);

            $organisation->users()->attach($user->id, [
                'role' => OrganisationUserRole::Admin->value,
                'status' => MembershipStatus::Approved->value,
                'granted_at' => now(),
                'approved_at' => now(),
            ]);

            $startsAt = now()->parse($validated['event_date'])->startOfDay();

            $event = Event::query()->create([
                'title' => trim($validated['event_name']),
                'host_organisation_id' => $organisation->id,
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()->addDay(),
                'all_day' => true,
                'status' => EventStatus::Draft,
                'description' => 'Venue city: '.trim($validated['venue_city']),
                'contact_email' => $user->email,
                'source' => ListingSource::Submission,
                'created_by' => $user->id,
            ]);

            $reference = 'SE-'.Str::upper(Str::random(20));

            $invoice = $this->invoiceNumbers->createInvoice([
                'organisation_id' => $organisation->id,
                'platform_subscription_id' => null,
                'platform_payment_id' => null,
                'issued_on' => today(),
                'period_start' => today(),
                'period_end' => today(),
                'description' => "Single-event listing â€” {$event->title}",
                'amount_excl_vat_cents' => $excl,
                'vat_cents' => $vat,
                'amount_incl_vat_cents' => $incl,
                'vat_bps' => PlatformFees::vatBps(),
                'currency' => 'ZAR',
                'paystack_reference' => $reference,
            ], today()->year);

            $init = $this->paystack->initializeOneOffTransaction(
                $user->email,
                $incl,
                route('organise.callback'),
                $reference,
                [
                    'purpose' => SingleEventPurchase::PURPOSE,
                    'organisation_id' => $organisation->id,
                    'event_id' => $event->id,
                    'platform_invoice_id' => $invoice->id,
                ],
            );

            $invoice->forceFill(['paystack_reference' => $init['reference']])->save();

            return [$user, $init['authorization_url']];
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->away($authorizationUrl);
    }

    public function callback(): View
    {
        return view('organise.callback');
    }

    /**
     * @return array{excl:int, vat:int, incl:int}
     */
    private function priceBreakdown(): array
    {
        $excl = (int) config('platform.single_event_price_cents', 75000);
        $vat = (int) round($excl * PlatformFees::vatBps() / 10000);

        return ['excl' => $excl, 'vat' => $vat, 'incl' => $excl + $vat];
    }
}
