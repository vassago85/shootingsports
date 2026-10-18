<?php

use App\Enums\EventStatus;
use App\Enums\ListingStatus;
use App\Enums\MembershipStatus;
use App\Enums\OrganisationKind;
use App\Enums\OrganisationUserRole;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\PlatformInvoice;
use App\Models\PlatformPayment;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->withoutVite();

    config([
        'services.paystack.secret_key' => 'sk_test_dummy',
        'services.paystack.base_url' => 'https://api.paystack.co',
    ]);
});

function singleEventPayload(): array
{
    return [
        'name' => 'Sam Organiser',
        'email' => 'sam@example.test',
        'password' => 'correct-horse-battery',
        'event_name' => 'Karoo Open 2027',
        'event_date' => now()->addMonths(3)->toDateString(),
        'venue_city' => 'Graaff-Reinet',
    ];
}

function submitSingleEvent(): void
{
    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'message' => 'Authorization URL created',
            'data' => [
                'authorization_url' => 'https://checkout.paystack.com/abc123',
                'access_code' => 'abc123',
                'reference' => 'SE-PAYSTACK-REF',
            ],
        ]),
    ]);

    test()->post(route('organise.submit'), singleEventPayload());
}

function makeSingleEventAdmin(): User
{
    $organisation = Organisation::factory()->create(['kind' => OrganisationKind::SingleEvent]);
    $user = User::factory()->create();
    $organisation->users()->attach($user->id, [
        'role' => OrganisationUserRole::Admin->value,
        'status' => MembershipStatus::Approved->value,
        'granted_at' => now(),
        'approved_at' => now(),
    ]);

    return $user;
}

function signedChargeSuccess(array $data): array
{
    $body = json_encode([
        'id' => 'evt_'.uniqid(),
        'event' => 'charge.success',
        'data' => $data,
    ]);

    return [$body, hash_hmac('sha512', $body, 'sk_test_dummy')];
}

it('GET /organise shows the signup form', function () {
    $this->get(route('organise.show'))
        ->assertOk()
        ->assertSee('List a single event')
        ->assertSee('R862.50')
        ->assertSee('name="event_name"', false);
});

it('POST /organise creates user + single_event org + Draft Event + PlatformInvoice and redirects to Paystack', function () {
    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'message' => 'Authorization URL created',
            'data' => [
                'authorization_url' => 'https://checkout.paystack.com/abc123',
                'access_code' => 'abc123',
                'reference' => 'SE-PAYSTACK-REF',
            ],
        ]),
    ]);

    $this->post(route('organise.submit'), singleEventPayload())
        ->assertRedirect('https://checkout.paystack.com/abc123');

    $user = User::query()->where('email', 'sam@example.test')->firstOrFail();
    $organisation = Organisation::query()->where('name', 'Karoo Open 2027')->firstOrFail();
    $event = Event::query()->where('host_organisation_id', $organisation->id)->firstOrFail();
    $invoice = PlatformInvoice::query()->where('organisation_id', $organisation->id)->firstOrFail();

    expect($user->email_verified_at)->not->toBeNull()
        ->and($organisation->kind)->toBe(OrganisationKind::SingleEvent)
        ->and($organisation->isSingleEvent())->toBeTrue()
        ->and($user->managedOrganisations()->whereKey($organisation->id)->exists())->toBeTrue()
        ->and($event->status)->toBe(EventStatus::Draft)
        ->and($invoice->platform_payment_id)->toBeNull()
        ->and($invoice->platform_subscription_id)->toBeNull()
        ->and($invoice->paystack_reference)->toBe('SE-PAYSTACK-REF')
        ->and($invoice->invoice_number)->toStartWith('INV-'.now()->year.'-');

    Http::assertSent(fn ($request) => $request['amount'] === 86250
        && $request['email'] === 'sam@example.test'
        && $request['metadata']['purpose'] === 'single_event'
        && $request['metadata']['event_id'] === $event->id
        && $request['metadata']['organisation_id'] === $organisation->id
        && $request['metadata']['platform_invoice_id'] === $invoice->id);

    $this->assertAuthenticatedAs($user);
});

it('POST /organise validates its input and creates nothing', function () {
    Http::fake();

    $this->post(route('organise.submit'), [...singleEventPayload(), 'event_date' => 'yesterday-ish', 'email' => 'nope'])
        ->assertSessionHasErrors(['event_date', 'email']);

    expect(User::query()->where('name', 'Sam Organiser')->exists())->toBeFalse()
        ->and(Organisation::query()->where('kind', 'single_event')->exists())->toBeFalse();

    Http::assertNothingSent();
});

it('rolls everything back when Paystack initialisation fails', function () {
    Http::fake(['api.paystack.co/transaction/initialize' => Http::response(['status' => false], 500)]);

    $this->withoutExceptionHandling();

    expect(fn () => $this->post(route('organise.submit'), singleEventPayload()))->toThrow(Exception::class);

    expect(User::query()->where('email', 'sam@example.test')->exists())->toBeFalse()
        ->and(PlatformInvoice::query()->count())->toBe(0)
        ->and(Event::query()->count())->toBe(0);
});

it('the invoice amount matches R750 excl + R112.50 VAT = R862.50 incl', function () {
    submitSingleEvent();

    $invoice = PlatformInvoice::query()->firstOrFail();

    expect($invoice->amount_excl_vat_cents)->toBe(75000)
        ->and($invoice->vat_cents)->toBe(11250)
        ->and($invoice->amount_incl_vat_cents)->toBe(86250)
        ->and($invoice->vat_bps)->toBe(1500)
        ->and($invoice->currency)->toBe('ZAR');
});

it('the webhook promotes the Event to Planned on charge.success', function () {
    submitSingleEvent();

    $invoice = PlatformInvoice::query()->firstOrFail();
    $event = Event::query()->firstOrFail();

    [$body, $signature] = signedChargeSuccess([
        'reference' => 'SE-PAYSTACK-REF',
        'amount' => 86250,
        'currency' => 'ZAR',
        'paid_at' => now()->toIso8601String(),
        'metadata' => [
            'purpose' => 'single_event',
            'organisation_id' => $invoice->organisation_id,
            'event_id' => $event->id,
            'platform_invoice_id' => $invoice->id,
        ],
    ]);

    $this->call('POST', '/paystack/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
    ], $body)->assertOk()->assertJson(['status' => 'ok']);

    $invoice->refresh();
    $payment = PlatformPayment::query()->where('paystack_reference', 'SE-PAYSTACK-REF')->firstOrFail();

    expect($event->fresh()->status)->toBe(EventStatus::Planned)
        ->and($payment->platform_subscription_id)->toBeNull()
        ->and($payment->amount_cents)->toBe(86250)
        ->and($invoice->platform_payment_id)->toBe($payment->id);
});

it('the webhook leaves the Event in Draft when the paid amount does not match the invoice', function () {
    submitSingleEvent();

    $event = Event::query()->firstOrFail();

    [$body, $signature] = signedChargeSuccess([
        'reference' => 'SE-PAYSTACK-REF',
        'amount' => 75000,
        'metadata' => ['purpose' => 'single_event', 'event_id' => $event->id],
    ]);

    $this->call('POST', '/paystack/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
    ], $body)->assertOk();

    expect($event->fresh()->status)->toBe(EventStatus::Draft)
        ->and(PlatformPayment::query()->count())->toBe(0);
});

it('Club nav resources are hidden for a user who only manages single_event orgs', function () {
    $admin = makeSingleEventAdmin();

    $this->actingAs($admin)->get('/desk/club-members')->assertForbidden();
    $this->actingAs($admin)->get('/desk/club-guest-visits')->assertForbidden();
    $this->actingAs($admin)->get('/desk/membership-invoices')->assertForbidden();
    $this->actingAs($admin)->get('/desk/membership-reports')->assertForbidden();
});

it('Club nav resources stay available for club admins', function () {
    $club = Organisation::factory()->create();
    $admin = User::factory()->create();
    $club->users()->attach($admin->id, [
        'role' => OrganisationUserRole::Admin->value,
        'status' => MembershipStatus::Approved->value,
        'granted_at' => now(),
        'approved_at' => now(),
    ]);

    $this->actingAs($admin)->get('/desk/club-members')->assertOk();
});

it('Billing pages remain accessible for single_event orgs', function () {
    $admin = makeSingleEventAdmin();

    $this->actingAs($admin)->get('/desk/billing')->assertOk();
});

function payForSingleEvent(): void
{
    $invoice = PlatformInvoice::query()->firstOrFail();
    $event = Event::query()->firstOrFail();

    [$body, $signature] = signedChargeSuccess([
        'reference' => 'SE-PAYSTACK-REF',
        'amount' => 86250,
        'currency' => 'ZAR',
        'paid_at' => now()->toIso8601String(),
        'metadata' => [
            'purpose' => 'single_event',
            'organisation_id' => $invoice->organisation_id,
            'event_id' => $event->id,
            'platform_invoice_id' => $invoice->id,
        ],
    ]);

    test()->call('POST', '/paystack/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
    ], $body)->assertOk();
}

it('after successful payment, the single_event organisation is published', function () {
    submitSingleEvent();

    $organisation = Organisation::query()->where('kind', 'single_event')->firstOrFail();
    expect($organisation->status)->toBe(ListingStatus::Pending);

    payForSingleEvent();

    expect($organisation->fresh()->status)->toBe(ListingStatus::Published);
});

it('after successful payment, Event::scopeUpcoming returns the paid event', function () {
    submitSingleEvent();

    $event = Event::query()->firstOrFail();
    expect(Event::upcoming()->pluck('id')->all())->not->toContain($event->id);

    payForSingleEvent();

    expect(Event::upcoming()->pluck('id')->all())->toContain($event->id);
});

it('invoice description is clean UTF-8', function () {
    submitSingleEvent();

    $invoice = PlatformInvoice::query()->firstOrFail();

    expect(preg_match('//u', $invoice->description))->toBe(1)
        ->and($invoice->description)->toBe("Single-event listing \u{2014} Karoo Open 2027")
        ->and($invoice->description)->not->toContain('â')
        ->and($invoice->description)->not->toContain('€');
});
