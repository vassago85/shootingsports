<?php

use App\Enums\ListingStatus;
use App\Enums\OrganisationType;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Venue;
use App\Support\EmbedTheme;
use App\Support\EmbedUrl;

beforeEach(function () {
    $this->withoutVite();
});

it('embeds only a published club\'s upcoming matches', function () {
    $club = Organisation::factory()->create([
        'slug' => 'embed-club',
        'name' => 'Embed Rifle Club',
        'status' => ListingStatus::Published,
        'type' => OrganisationType::Club,
    ]);
    $other = Organisation::factory()->create([
        'slug' => 'other-embed-club',
        'status' => ListingStatus::Published,
    ]);
    Event::factory()->confirmed()->create([
        'title' => 'Club Night at Embed',
        'host_organisation_id' => $club->id,
        'starts_at' => now()->addWeek(),
    ]);
    Event::factory()->confirmed()->create([
        'title' => 'Someone Else Match',
        'host_organisation_id' => $other->id,
        'starts_at' => now()->addWeek(),
    ]);

    $this->get(route('embed.calendar', ['club' => 'embed-club']))
        ->assertOk()
        ->assertSee('Embed Rifle Club calendar')
        ->assertSee('Club Night at Embed')
        ->assertDontSee('Someone Else Match');
});

it('embeds matches at a published range', function () {
    $venue = Venue::factory()->create([
        'slug' => 'embed-range',
        'name' => 'Embed Valley',
        'status' => ListingStatus::Published,
    ]);
    $other = Venue::factory()->create([
        'slug' => 'other-embed-range',
        'status' => ListingStatus::Published,
    ]);
    Event::factory()->confirmed()->create([
        'title' => 'Valley Open',
        'venue_id' => $venue->id,
        'starts_at' => now()->addWeek(),
    ]);
    Event::factory()->confirmed()->create([
        'title' => 'Elsewhere Open',
        'venue_id' => $other->id,
        'starts_at' => now()->addWeek(),
    ]);

    $this->get(route('embed.calendar', ['venue' => 'embed-range']))
        ->assertOk()
        ->assertSee('Embed Valley calendar')
        ->assertSee('Valley Open')
        ->assertDontSee('Elsewhere Open');
});

it('ignores unpublished listings and invalid theme values', function () {
    Organisation::factory()->create([
        'slug' => 'draft-embed-club',
        'name' => 'Hidden Draft Club',
        'status' => ListingStatus::Pending,
    ]);

    $this->get(route('embed.calendar', [
        'club' => 'draft-embed-club',
        'accent' => 'javascript:alert(1)',
        'font' => 'comic-sans',
        'theme' => 'neon',
    ]))
        ->assertOk()
        ->assertDontSee('Hidden Draft Club')
        ->assertDontSee('javascript:')
        ->assertDontSee('comic-sans');
});

it('serves embed script attributes for club styling', function () {
    $this->get(route('embed.script'))
        ->assertOk()
        ->assertHeader('content-type', 'application/javascript; charset=utf-8')
        ->assertSee('organisation')
        ->assertSee('accent')
        ->assertSee('font');
});

it('hides embed snippets from guests and shows them when signed in', function () {
    $club = Organisation::factory()->create([
        'slug' => 'snippet-club',
        'name' => 'Snippet Club',
        'status' => ListingStatus::Published,
        'type' => OrganisationType::Club,
    ]);
    $venue = Venue::factory()->create([
        'slug' => 'snippet-range',
        'name' => 'Snippet Range',
        'status' => ListingStatus::Published,
    ]);
    $federation = Organisation::factory()->create([
        'slug' => 'snippet-fed',
        'name' => 'Snippet Federation',
        'status' => ListingStatus::Published,
        'type' => OrganisationType::Federation,
    ]);

    $this->get(route('clubs.show', $club->slug))
        ->assertOk()
        ->assertSee('Sign in to embed this calendar')
        ->assertDontSee('embed/calendar?club=snippet-club', false);

    $this->actingAs(User::factory()->create())
        ->get(route('clubs.show', $club->slug))
        ->assertOk()
        ->assertSee('embed/calendar?club=snippet-club', false)
        ->assertSee('Copy WordPress URL')
        ->assertSee('Copy iframe');

    $this->actingAs(User::factory()->create())
        ->get(route('ranges.show', $venue->slug))
        ->assertOk()
        ->assertSee('embed/calendar?venue=snippet-range', false);

    $this->actingAs(User::factory()->create())
        ->get(route('federations.show', $federation->slug))
        ->assertOk()
        ->assertSee('embed/calendar?organisation=snippet-fed', false);
});

it('advertises oEmbed discovery and allows the calendar to be framed', function () {
    $this->get(route('embed.calendar', ['club' => 'embed-club']))
        ->assertOk()
        ->assertSee('application/json+oembed', false)
        ->assertHeader('content-security-policy', 'frame-ancestors *')
        ->assertHeaderMissing('x-frame-options');
});

it('returns oEmbed JSON for a same-host calendar URL', function () {
    Organisation::factory()->create([
        'slug' => 'oembed-club',
        'name' => 'OEmbed Rifle Club',
        'status' => ListingStatus::Published,
        'type' => OrganisationType::Club,
    ]);

    $url = route('embed.calendar', ['club' => 'oembed-club', 'theme' => 'dark']);

    $this->get(route('oembed', ['url' => $url, 'maxwidth' => 700]))
        ->assertOk()
        ->assertJsonPath('version', '1.0')
        ->assertJsonPath('type', 'rich')
        ->assertJsonPath('title', 'OEmbed Rifle Club')
        ->assertJsonPath('width', 700)
        ->assertJsonPath('provider_name', 'Shooting Sports')
        ->assertSee('iframe', false)
        ->assertSee('club=oembed-club', false)
        ->assertSee('theme=dark', false)
        ->assertDontSee('javascript:', false);
});

it('rejects oEmbed URLs that are not this calendar', function () {
    $this->get(route('oembed', ['url' => 'https://evil.example/embed/calendar?club=x']))
        ->assertNotFound();

    $this->get(route('oembed', ['url' => url('/embed/calendar.js')]))
        ->assertNotFound();

    $this->get(route('oembed'))
        ->assertNotFound();
});

it('documents the WordPress iframe path', function () {
    $this->get(route('embed.docs'))
        ->assertOk()
        ->assertSee('For WordPress')
        ->assertSee('Custom HTML')
        ->assertSee('iframe', false);
});

it('only accepts same-host embed calendar URLs', function () {
    expect(EmbedUrl::fromString(route('embed.calendar', ['club' => 'send-it-elr'])))
        ->not->toBeNull()
        ->calendarUrl->toContain('club=send-it-elr');

    expect(EmbedUrl::fromString('https://evil.example/embed/calendar?club=x'))->toBeNull()
        ->and(EmbedUrl::fromString('javascript:alert(1)'))->toBeNull()
        ->and(EmbedUrl::fromString(url('/embed/calendar.js')))->toBeNull();
});

it('only accepts hex colours and allow-listed fonts', function () {
    expect(EmbedTheme::hex('#D9AE52'))->toBe('#D9AE52')
        ->and(EmbedTheme::hex('1a5c3a'))->toBe('#1a5c3a')
        ->and(EmbedTheme::hex('red'))->toBeNull()
        ->and(EmbedTheme::hex('url(evil)'))->toBeNull();

    $theme = EmbedTheme::fromRequest(request()->merge([
        'font' => 'inter',
        'theme' => 'dark',
        'accent' => '#abc',
    ]));

    expect($theme->font)->toBe('inter')
        ->and($theme->theme)->toBe('dark')
        ->and($theme->accent)->toBe('#abc')
        ->and($theme->cssVariables())->toContain('--f-display:');
});
