<?php

use App\Enums\ListingStatus;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrganisationType;
use App\Enums\OrganisationUserRole;
use App\Filament\Desk\Resources\Organisations\OrganisationResource;
use App\Filament\Desk\Resources\Venues\VenueResource;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\OrganisationUser;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueMembership;
use App\Support\EmbedTheme;
use App\Support\EmbedUrl;

function grantClubSeat(User $user, Organisation $club, OrganisationUserRole $role, MembershipStatus $status = MembershipStatus::Approved): void
{
    OrganisationUser::query()->create([
        'user_id' => $user->id,
        'organisation_id' => $club->id,
        'role' => $role->value,
        'status' => $status->value,
        'approved_at' => $status === MembershipStatus::Approved ? now() : null,
        'granted_at' => now(),
    ]);
}

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

it('hides club admin tools from guests and from people who do not run that club', function () {
    $owner = User::factory()->create();
    $club = Organisation::factory()->create([
        'slug' => 'snippet-club',
        'name' => 'Snippet Club',
        'status' => ListingStatus::Published,
        'type' => OrganisationType::Club,
        'claimed_by' => $owner->id,
    ]);
    grantClubSeat($owner, $club, OrganisationUserRole::Admin);

    $this->get(route('clubs.show', $club->slug))
        ->assertOk()
        ->assertDontSee('For club administrators')
        ->assertDontSee('Director login')
        ->assertDontSee('Sign in to embed this calendar on your own site')
        ->assertDontSee('WordPress URL')
        ->assertDontSee('embed/calendar?club=snippet-club', false);

    $this->actingAs(User::factory()->create())
        ->get(route('clubs.show', $club->slug))
        ->assertOk()
        ->assertDontSee('For club administrators')
        ->assertDontSee('embed/calendar?club=snippet-club', false);

    $this->actingAs(User::factory()->matchDirector()->create())
        ->get(route('clubs.show', $club->slug))
        ->assertOk()
        ->assertDontSee('For club administrators')
        ->assertDontSee('embed/calendar?club=snippet-club', false);
});

it('shows club admin tools only to a match director or club admin of that club', function () {
    $matchDirector = User::factory()->create();
    $clubAdmin = User::factory()->create();
    $editor = User::factory()->create();
    $secretary = User::factory()->create();
    $pendingDirector = User::factory()->create();
    $club = Organisation::factory()->create([
        'slug' => 'snippet-club',
        'name' => 'Snippet Club',
        'status' => ListingStatus::Published,
        'type' => OrganisationType::Club,
        'claimed_by' => $clubAdmin->id,
    ]);
    $other = Organisation::factory()->create([
        'slug' => 'other-snippet-club',
        'status' => ListingStatus::Published,
        'type' => OrganisationType::Club,
        'claimed_by' => $matchDirector->id,
    ]);

    grantClubSeat($matchDirector, $club, OrganisationUserRole::MatchDirector);
    grantClubSeat($matchDirector, $other, OrganisationUserRole::MatchDirector);
    grantClubSeat($clubAdmin, $club, OrganisationUserRole::Admin);
    grantClubSeat($editor, $club, OrganisationUserRole::Editor);
    grantClubSeat($secretary, $club, OrganisationUserRole::Secretary);
    grantClubSeat($pendingDirector, $club, OrganisationUserRole::MatchDirector, MembershipStatus::Pending);

    $manageUrl = OrganisationResource::getUrl('edit', ['record' => $club], panel: 'desk');

    $this->actingAs($matchDirector)
        ->get(route('clubs.show', $club->slug))
        ->assertOk()
        ->assertSee('For club administrators')
        ->assertSee('embed/calendar?club=snippet-club', false)
        ->assertSee('WordPress URL')
        ->assertSee('Iframe')
        ->assertSee('Copy')
        ->assertSee('Manage this club')
        ->assertSee($manageUrl, false)
        ->assertDontSee('Director login')
        ->assertDontSee('embed/calendar?club=other-snippet-club', false);

    $this->actingAs($matchDirector)
        ->get(route('clubs.show', $other->slug))
        ->assertOk()
        ->assertSee('embed/calendar?club=other-snippet-club', false)
        ->assertDontSee('embed/calendar?club=snippet-club', false);

    $this->actingAs($clubAdmin)
        ->get(route('clubs.show', $club->slug))
        ->assertOk()
        ->assertSee('embed/calendar?club=snippet-club', false)
        ->assertSee('Manage this club');

    $this->actingAs($editor)
        ->get(route('clubs.show', $club->slug))
        ->assertOk()
        ->assertDontSee('For club administrators')
        ->assertDontSee('embed/calendar?club=snippet-club', false);

    $this->actingAs($secretary)
        ->get(route('clubs.show', $club->slug))
        ->assertOk()
        ->assertDontSee('For club administrators');

    $this->actingAs($pendingDirector)
        ->get(route('clubs.show', $club->slug))
        ->assertOk()
        ->assertDontSee('For club administrators')
        ->assertDontSee('embed/calendar?club=snippet-club', false);
});

it('keeps the claim link on an unclaimed club and still hides the embed', function () {
    $club = Organisation::factory()->create([
        'slug' => 'unclaimed-snippet-club',
        'status' => ListingStatus::Published,
        'type' => OrganisationType::Club,
        'claimed_by' => null,
    ]);

    $this->get(route('clubs.show', $club->slug))
        ->assertOk()
        ->assertSee('This is my club')
        ->assertDontSee('WordPress URL')
        ->assertDontSee('embed/calendar?club=unclaimed-snippet-club', false)
        ->assertDontSee('Director login');
});

it('shows range and federation embed tools only to the owner of that listing', function () {
    $stranger = User::factory()->create();
    $rangeOwner = User::factory()->create();
    $federationAdmin = User::factory()->create();
    $venue = Venue::factory()->create([
        'slug' => 'snippet-range',
        'name' => 'Snippet Range',
        'status' => ListingStatus::Published,
        'claimed_by' => $rangeOwner->id,
    ]);
    VenueMembership::query()->create([
        'venue_id' => $venue->id,
        'user_id' => $rangeOwner->id,
        'role' => MembershipRole::Owner->value,
        'status' => MembershipStatus::Approved->value,
        'approved_at' => now(),
    ]);
    $federation = Organisation::factory()->create([
        'slug' => 'snippet-fed',
        'name' => 'Snippet Federation',
        'status' => ListingStatus::Published,
        'type' => OrganisationType::Federation,
        'claimed_by' => $federationAdmin->id,
    ]);
    grantClubSeat($federationAdmin, $federation, OrganisationUserRole::Admin);

    $this->actingAs($stranger)
        ->get(route('ranges.show', $venue->slug))
        ->assertOk()
        ->assertDontSee('For range operators')
        ->assertDontSee('embed/calendar?venue=snippet-range', false);

    $this->actingAs($rangeOwner)
        ->get(route('ranges.show', $venue->slug))
        ->assertOk()
        ->assertSee('embed/calendar?venue=snippet-range', false)
        ->assertSee('Manage this range')
        ->assertSee(VenueResource::getUrl('edit', ['record' => $venue], panel: 'desk'), false);

    $this->actingAs($stranger)
        ->get(route('federations.show', $federation->slug))
        ->assertOk()
        ->assertDontSee('For federation administrators')
        ->assertDontSee('embed/calendar?organisation=snippet-fed', false);

    $this->actingAs($federationAdmin)
        ->get(route('federations.show', $federation->slug))
        ->assertOk()
        ->assertSee('embed/calendar?organisation=snippet-fed', false)
        ->assertSee('Manage this federation');
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
