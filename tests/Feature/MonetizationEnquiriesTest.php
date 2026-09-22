<?php

use App\Enums\AdPage;
use App\Enums\EnquiryStatus;
use App\Enums\ListingStatus;
use App\Enums\PlacementSlot;
use App\Enums\ProviderCategory;
use App\Enums\ProviderTier;
use App\Mail\EnquiryReceivedMail;
use App\Models\AdSlot;
use App\Models\Enquiry;
use App\Models\Placement;
use App\Models\Provider;
use App\Models\User;
use App\Models\Venue;
use App\Support\AdPlacements;
use App\Support\MailSettings;
use App\Support\Turnstile;
use App\Support\TurnstileSettings;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    $this->withoutVite();
});

it('stores mailgun settings encrypted and applies them to config', function () {
    MailSettings::save([
        'mailer' => 'mailgun',
        'from_address' => 'hello@shootingsports.test',
        'from_name' => 'Shooting Sports',
        'mailgun_domain' => 'mg.shootingsports.test',
        'mailgun_secret' => 'key-secret-value',
        'mailgun_endpoint' => 'api.eu.mailgun.net',
    ]);

    expect(config('mail.default'))->toBe('mailgun')
        ->and(config('mail.from.address'))->toBe('hello@shootingsports.test')
        ->and(config('services.mailgun.domain'))->toBe('mg.shootingsports.test')
        ->and(config('services.mailgun.secret'))->toBe('key-secret-value');

    MailSettings::save([
        'mailer' => 'mailgun',
        'from_address' => 'hello@shootingsports.test',
        'from_name' => 'Shooting Sports',
        'mailgun_domain' => 'mg.shootingsports.test',
        'mailgun_secret' => '',
        'mailgun_endpoint' => 'api.eu.mailgun.net',
    ]);

    expect(config('services.mailgun.secret'))->toBe('key-secret-value');
});

it('stores turnstile settings encrypted and applies them to config', function () {
    TurnstileSettings::save([
        'site_key' => '0xsite-public',
        'secret_key' => '0xsecret-private',
    ]);

    expect(config('services.turnstile.site_key'))->toBe('0xsite-public')
        ->and(config('services.turnstile.secret_key'))->toBe('0xsecret-private')
        ->and(Turnstile::isConfigured())->toBeTrue();

    TurnstileSettings::save([
        'site_key' => '0xsite-public',
        'secret_key' => '',
    ]);

    expect(config('services.turnstile.secret_key'))->toBe('0xsecret-private');
});

it('accepts a contact enquiry and emails staff', function () {
    Mail::fake();

    User::factory()->create([
        'email' => 'staff@shootingsports.test',
        'is_staff' => true,
    ]);

    $this->post(route('enquiries.store'), [
        'name' => 'Alex Shooter',
        'email' => 'alex@example.test',
        'phone' => null,
        'subject' => 'Hello',
        'body' => 'Please tell me how to list our club.',
        'type' => 'general',
        'company_website' => '',
        'form_loaded_at' => time() - 10,
    ])->assertRedirect(route('enquiries.thanks'));

    expect(Enquiry::query()->count())->toBe(1);

    Mail::assertQueued(EnquiryReceivedMail::class);
});

it('rejects honeypot and too-fast enquiry submissions', function () {
    $this->from(route('contact'))
        ->post(route('enquiries.store'), [
            'name' => 'Bot',
            'email' => 'bot@example.test',
            'body' => 'Spam body text here',
            'type' => 'general',
            'company_website' => 'https://spam.test',
            'form_loaded_at' => time() - 10,
        ])
        ->assertSessionHasErrors('body');

    $this->from(route('contact'))
        ->post(route('enquiries.store'), [
            'name' => 'Fast',
            'email' => 'fast@example.test',
            'body' => 'Too quick submission',
            'type' => 'general',
            'company_website' => '',
            'form_loaded_at' => time(),
        ])
        ->assertSessionHasErrors('body');

    expect(Enquiry::query()->count())->toBe(0);
});

it('rate limits enquiry posts per IP', function () {
    RateLimiter::clear('enquiry:127.0.0.1');

    $payload = [
        'name' => 'Alex',
        'email' => 'alex@example.test',
        'body' => 'Please tell me how to list our club.',
        'type' => 'general',
        'company_website' => '',
        'form_loaded_at' => time() - 10,
    ];

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('enquiries.store'), $payload)->assertRedirect(route('enquiries.thanks'));
    }

    $this->from(route('contact'))
        ->post(route('enquiries.store'), $payload)
        ->assertSessionHasErrors('body');
});

it('hides supplier emails and shows enquire CTA', function () {
    $provider = Provider::factory()->create([
        'slug' => 'quiet-gunsmith',
        'name' => 'Quiet Gunsmith',
        'email' => 'secret@shop.test',
        'phone' => '0820000000',
        'status' => ListingStatus::Published,
    ]);

    $this->get(route('suppliers.show', $provider->slug))
        ->assertOk()
        ->assertSee('Enquire via platform')
        ->assertDontSee('secret@shop.test')
        ->assertDontSee('0820000000');
});

it('sorts featured providers ahead of free listings', function () {
    Provider::factory()->create([
        'name' => 'Zebra Free Shop',
        'slug' => 'zebra-free',
        'tier' => ProviderTier::Free,
        'status' => ListingStatus::Published,
        'category' => ProviderCategory::Dealer,
    ]);
    Provider::factory()->create([
        'name' => 'Alpha Featured Shop',
        'slug' => 'alpha-featured',
        'tier' => ProviderTier::Featured,
        'status' => ListingStatus::Published,
        'category' => ProviderCategory::Dealer,
    ]);

    $html = $this->get(route('suppliers.category', 'dealer'))->assertOk()->getContent();

    expect(strpos($html, 'Alpha Featured Shop'))->toBeLessThan(strpos($html, 'Zebra Free Shop'));
});

it('renders active placements for a page slot', function () {
    $provider = Provider::factory()->create(['status' => ListingStatus::Published]);
    $slot = AdSlot::query()->create([
        'page' => AdPage::Home,
        'slot' => PlacementSlot::Leaderboard,
        'name' => 'Home leaderboard',
        'price_cents' => 250000,
        'is_active' => true,
    ]);

    Placement::query()->create([
        'provider_id' => $provider->id,
        'ad_slot_id' => $slot->id,
        'slot' => PlacementSlot::Leaderboard,
        'headline' => 'Buy Brass Here',
        'body' => 'Featured supplier on the register.',
        'starts_on' => now()->subDay()->toDateString(),
        'ends_on' => now()->addMonth()->toDateString(),
        'rate_cents' => 250000,
        'impressions' => 0,
        'clicks' => 0,
        'is_active' => true,
    ]);

    expect(AdPlacements::for(AdPage::Home, PlacementSlot::Leaderboard))->toHaveCount(1);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('Buy Brass Here')
        ->assertDontSee('This space is available');
});

it('suppresses the vacant "Advertise here" pitch on every public browsing surface', function () {
    // UX audit #13: the vacant pitch above the first row of matches
    // was flagged as thin — every content page (home, calendar,
    // ranges, suppliers) now passes hide-when-vacant so an unsold
    // slot renders no chrome at all. The /advertise sales page is
    // where the pitch lives now, not shoulder-tapping every visitor.
    foreach ([route('home'), route('calendar'), route('ranges.index'), route('suppliers.index')] as $url) {
        $response = $this->get($url)->assertOk();

        expect($response->getContent())
            ->not->toContain('This space is available')
            ->not->toContain('ss-partner-open');
    }
});

it('marks enquiries read when opened', function () {
    $enquiry = Enquiry::query()->create([
        'type' => 'general',
        'name' => 'Alex',
        'email' => 'alex@example.test',
        'body' => 'Hello there staff',
        'status' => EnquiryStatus::New,
    ]);

    $enquiry->markRead();

    expect($enquiry->fresh()->status)->toBe(EnquiryStatus::Read)
        ->and($enquiry->fresh()->read_at)->not->toBeNull();
});

it('supports paid tiers on venues', function () {
    $venue = Venue::factory()->create([
        'name' => 'Featured Range',
        'slug' => 'featured-range',
        'tier' => ProviderTier::Featured,
        'status' => ListingStatus::Published,
    ]);

    $this->get(route('ranges.show', $venue->slug))
        ->assertOk()
        ->assertSee('Featured');
});
