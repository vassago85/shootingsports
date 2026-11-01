<?php

use App\Enums\ListingStatus;
use App\Enums\OrganisationType;
use App\Models\Event;
use App\Models\Organisation;
use App\Support\JsonLd;
use App\Support\SocialLinks;

it('normalises and orders organisation social links', function () {
    $org = Organisation::factory()->create([
        'website_url' => 'https://example.test',
        'facebook_url' => 'https://facebook.com/example',
        'instagram_url' => 'https://instagram.com/example',
        'whatsapp_url' => 'https://chat.whatsapp.com/example',
    ]);

    $keys = collect($org->socialLinks())->pluck('key')->all();

    expect($keys)->toBe(['website', 'facebook', 'instagram', 'whatsapp']);
});

it('skips empty organisation social URLs', function () {
    $org = Organisation::factory()->create([
        'website_url' => null,
        'facebook_url' => 'https://facebook.com/royal-flush',
        'instagram_url' => '   ',
        'whatsapp_url' => null,
    ]);

    expect($org->socialLinks())->toHaveCount(1)
        ->and($org->socialLinks()[0]['key'])->toBe('facebook');
});

it('falls back to the host organisation for missing event social links', function () {
    $series = Organisation::factory()->create([
        'type' => OrganisationType::Series,
        'facebook_url' => 'https://facebook.com/host-series',
        'instagram_url' => 'https://instagram.com/host-series',
        'whatsapp_url' => null,
        'website_url' => null,
    ]);

    $event = Event::factory()->create([
        'host_organisation_id' => $series->id,
        // Event only overrides Facebook; everything else inherits.
        'facebook_url' => 'https://facebook.com/this-specific-match',
        'instagram_url' => null,
        'whatsapp_url' => null,
        'website_url' => null,
    ]);

    $links = collect($event->fresh('hostOrganisation')->socialLinks())
        ->keyBy('key')
        ->map(fn (array $link): string => $link['url'])
        ->all();

    expect($links)->toBe([
        'facebook' => 'https://facebook.com/this-specific-match',
        'instagram' => 'https://instagram.com/host-series',
    ]);
});

it('renders the social links row on a published club page', function () {
    $club = Organisation::factory()->create([
        'slug' => 'test-club-social',
        'name' => 'Test Social Club',
        'type' => OrganisationType::Club,
        'status' => ListingStatus::Published,
        'facebook_url' => 'https://facebook.com/test-social-club',
        'instagram_url' => 'https://instagram.com/test-social-club',
    ]);

    $this->get(route('clubs.show', $club->slug))
        ->assertOk()
        ->assertSee('https://facebook.com/test-social-club', false)
        ->assertSee('https://instagram.com/test-social-club', false)
        ->assertSee('aria-label="Facebook (opens in a new tab)"', false)
        ->assertSee('aria-label="Instagram (opens in a new tab)"', false);
});

it('renders inherited event social links on the public match page', function () {
    $series = Organisation::factory()->create([
        'type' => OrganisationType::Series,
        'status' => ListingStatus::Published,
        'facebook_url' => 'https://facebook.com/series-only',
        'whatsapp_url' => 'https://chat.whatsapp.com/series-only',
    ]);

    $event = Event::factory()->create([
        'slug' => 'social-fallback-match',
        'host_organisation_id' => $series->id,
    ]);

    $this->get(route('events.show', $event->slug))
        ->assertOk()
        ->assertSee('https://facebook.com/series-only', false)
        ->assertSee('https://chat.whatsapp.com/series-only', false);
});

it('exposes new social URLs in the organisation JSON-LD sameAs', function () {
    $links = SocialLinks::fromArray([
        'website_url' => 'https://a.test',
        'facebook_url' => 'https://b.test',
        'instagram_url' => 'https://c.test',
        'whatsapp_url' => 'https://d.test',
    ]);

    expect($links)->toHaveCount(4);

    $org = Organisation::factory()->create([
        'website_url' => 'https://a.test',
        'facebook_url' => 'https://b.test',
        'instagram_url' => 'https://c.test',
        'whatsapp_url' => 'https://d.test',
    ]);

    $jsonLd = JsonLd::organisation($org);

    expect($jsonLd['sameAs'])->toBe([
        'https://a.test',
        'https://b.test',
        'https://c.test',
        'https://d.test',
    ]);
});
