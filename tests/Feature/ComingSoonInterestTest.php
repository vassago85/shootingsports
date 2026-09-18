<?php

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Enums\PrelaunchContributorRole;
use App\Mail\EnquiryReceivedMail;
use App\Mail\PrelaunchContributorConfirmMail;
use App\Models\Enquiry;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function (): void {
    $this->withoutVite();
    RateLimiter::clear('coming-soon-interest:127.0.0.1');
    config()->set('services.turnstile.site_key', null);
    config()->set('services.turnstile.secret_key', null);
});

function interestPayload(array $overrides = []): array
{
    return array_merge([
        'role' => PrelaunchContributorRole::Club->value,
        'name' => 'Dana Director',
        'email' => 'dana@example.test',
        'body' => 'We run weekly club matches in Gauteng.',
        'company_website' => '',
        'form_loaded_at' => time() - 10,
    ], $overrides);
}

it('allows coming-soon interest posts when the gate is on', function () {
    config()->set('coming-soon.enabled', true);
    Mail::fake();

    $this->post(route('coming-soon.interest'), interestPayload())
        ->assertRedirect(route('coming-soon'))
        ->assertSessionHas('interest_status', 'check_email');

    expect(Enquiry::query()->count())->toBe(1);
});

it('still redirects contact enquiries when the gate is on', function () {
    config()->set('coming-soon.enabled', true);

    $this->get(route('contact'))->assertRedirect(route('coming-soon'));
});

it('stores a pending contributor and queues confirm mail but not staff mail', function () {
    Mail::fake();

    $this->post(route('coming-soon.interest'), interestPayload())
        ->assertRedirect(route('coming-soon'));

    $enquiry = Enquiry::query()->first();

    expect($enquiry)->not->toBeNull()
        ->and($enquiry->type)->toBe(EnquiryType::PrelaunchContributor)
        ->and($enquiry->status)->toBe(EnquiryStatus::PendingConfirmation)
        ->and($enquiry->context)->toBe(['role' => 'club'])
        ->and($enquiry->confirmation_token)->not->toBeNull()
        ->and($enquiry->confirmation_sent_at)->not->toBeNull();

    Mail::assertQueued(PrelaunchContributorConfirmMail::class);
    Mail::assertNotQueued(EnquiryReceivedMail::class);
});

it('confirms a valid token, clears it, and emails staff', function () {
    Mail::fake();

    User::factory()->create([
        'email' => 'staff@shootingsports.test',
        'is_staff' => true,
    ]);

    $this->post(route('coming-soon.interest'), interestPayload());

    $enquiry = Enquiry::query()->first();
    $token = $enquiry->confirmation_token;

    $this->get(route('coming-soon.confirm', $token))
        ->assertOk()
        ->assertSee('Interest confirmed', false);

    $enquiry->refresh();

    expect($enquiry->status)->toBe(EnquiryStatus::New)
        ->and($enquiry->confirmation_token)->toBeNull()
        ->and($enquiry->confirmed_at)->not->toBeNull();

    Mail::assertQueued(EnquiryReceivedMail::class);
});

it('rejects an expired or unknown confirmation token without changing status', function () {
    Mail::fake();

    $this->post(route('coming-soon.interest'), interestPayload());

    $enquiry = Enquiry::query()->first();
    $enquiry->forceFill([
        'confirmation_sent_at' => now()->subHours(Enquiry::CONFIRMATION_TTL_HOURS + 1),
    ])->save();

    $this->get(route('coming-soon.confirm', $enquiry->confirmation_token))
        ->assertOk()
        ->assertSee('Link expired', false);

    expect($enquiry->fresh()->status)->toBe(EnquiryStatus::PendingConfirmation);

    $this->get(route('coming-soon.confirm', str_repeat('a', 64)))
        ->assertOk()
        ->assertSee('Link not valid', false);

    Mail::assertNotQueued(EnquiryReceivedMail::class);
});

it('rejects turnstile failures when configured', function () {
    config()->set('services.turnstile.site_key', 'site-test');
    config()->set('services.turnstile.secret_key', 'secret-test');

    Http::fake([
        'challenges.cloudflare.com/*' => Http::response(['success' => false], 200),
    ]);

    Mail::fake();

    $this->from(route('coming-soon'))
        ->post(route('coming-soon.interest'), interestPayload([
            'cf-turnstile-response' => 'bad-token',
        ]))
        ->assertSessionHasErrors('cf-turnstile-response');

    expect(Enquiry::query()->count())->toBe(0);
    Mail::assertNothingQueued();
});

it('resends confirmation for an existing pending email without duplicating rows', function () {
    Mail::fake();

    $this->post(route('coming-soon.interest'), interestPayload());
    $firstToken = Enquiry::query()->first()->confirmation_token;

    $this->post(route('coming-soon.interest'), interestPayload([
        'name' => 'Dana Updated',
        'body' => 'Updated note about our club calendar help.',
        'role' => PrelaunchContributorRole::Range->value,
    ]));

    expect(Enquiry::query()->count())->toBe(1);

    $enquiry = Enquiry::query()->first();

    expect($enquiry->name)->toBe('Dana Updated')
        ->and($enquiry->context['role'])->toBe('range')
        ->and($enquiry->confirmation_token)->not->toBe($firstToken);

    Mail::assertQueued(PrelaunchContributorConfirmMail::class, 2);
});

it('blocks a second confirmed interest for the same email and role', function () {
    Mail::fake();

    User::factory()->create(['email' => 'staff@shootingsports.test', 'is_staff' => true]);

    $this->post(route('coming-soon.interest'), interestPayload());
    $token = Enquiry::query()->first()->confirmation_token;
    $this->get(route('coming-soon.confirm', $token))->assertOk();

    $this->from(route('coming-soon'))
        ->post(route('coming-soon.interest'), interestPayload())
        ->assertSessionHasErrors('email');

    expect(Enquiry::query()->count())->toBe(1);
});

it('excludes pending confirmation from the staff new-enquiries count', function () {
    Enquiry::query()->create([
        'type' => EnquiryType::PrelaunchContributor,
        'name' => 'Pending',
        'email' => 'pending@example.test',
        'subject' => 'Pre-launch: Club',
        'body' => 'Still waiting to confirm email address.',
        'context' => ['role' => 'club'],
        'status' => EnquiryStatus::PendingConfirmation,
    ]);

    Enquiry::query()->create([
        'type' => EnquiryType::General,
        'name' => 'Ready',
        'email' => 'ready@example.test',
        'subject' => 'Hello',
        'body' => 'A normal actionable enquiry body.',
        'status' => EnquiryStatus::New,
    ]);

    expect(Enquiry::query()->where('status', EnquiryStatus::New)->count())->toBe(1);
});

it('renders the interest form on the coming-soon page', function () {
    $this->get(route('coming-soon'))
        ->assertOk()
        ->assertSee('Help us load the register', false)
        ->assertSee('name="role"', false);
});
