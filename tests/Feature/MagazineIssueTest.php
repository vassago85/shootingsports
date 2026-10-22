<?php

use App\Filament\Desk\Resources\MagazineIssues\MagazineIssueResource;
use App\Models\MagazineIssue;
use App\Models\MagazineRead;
use App\Models\User;
use Illuminate\Support\Facades\URL;

beforeEach(function (): void {
    $this->withoutVite();
    config()->set('coming-soon.enabled', false);
    config()->set('seo.indexable', true);
});

// ============================================================
// Public pages: drafts, previews, published
// ============================================================

it('hides draft issues from the public with a 404', function () {
    $issue = MagazineIssue::factory()->draft()->create();

    $this->get(route('magazines.show', $issue->slug))->assertNotFound();
});

it('shows a draft issue to anyone with a valid signed preview link', function () {
    $issue = MagazineIssue::factory()->draft()->create([
        'title' => 'Private Pilot Issue',
    ]);

    $url = URL::temporarySignedRoute(
        'magazines.show',
        now()->addDays(14),
        ['issue' => $issue->slug]
    );

    $this->get($url)
        ->assertOk()
        ->assertSee('Private Pilot Issue', false)
        ->assertSee('private preview of a draft issue', false)
        // Previews never advertise themselves to search engines.
        ->assertSee('noindex,nofollow', false);
});

it('serves a published issue to the public with the flipbook mount', function () {
    $issue = MagazineIssue::factory()->published()->create([
        'title' => 'October 2026 Issue',
    ]);

    $this->get(route('magazines.show', $issue->slug))
        ->assertOk()
        ->assertSee('October 2026 Issue', false)
        ->assertSee('id="magazine-flipbook"', false)
        ->assertSee('Full screen', false)
        ->assertSee('data-track="1"', false)
        ->assertDontSee('noindex,nofollow', false);
});

it('hides the text version when every extracted page is blank', function () {
    $issue = MagazineIssue::factory()->published()->create([
        'pages_text' => ['', '   '],
    ]);

    $this->get(route('magazines.show', $issue->slug))
        ->assertOk()
        ->assertDontSee('id="magazine-text"', false)
        ->assertDontSee('Read as text', false);
});

it('shows the text version when a page has extracted copy', function () {
    $issue = MagazineIssue::factory()->published()->create([
        'pages_text' => ['', 'Proteas on target'],
    ]);

    $this->get(route('magazines.show', $issue->slug))
        ->assertOk()
        ->assertSee('id="magazine-text"', false)
        ->assertSee('Proteas on target', false)
        ->assertSee('Page 2', false)
        ->assertDontSee('Page 1', false);
});

it('lists published issues on the magazines index with ItemList schema', function () {
    MagazineIssue::factory()->published()->create(['title' => 'Published issue A']);
    MagazineIssue::factory()->draft()->create(['title' => 'Draft issue B']);

    $this->get(route('magazines.index'))
        ->assertOk()
        ->assertSee('Published issue A', false)
        ->assertDontSee('Draft issue B', false)
        ->assertSee('"@type":"ItemList"', false);
});

// ============================================================
// Read tracking: dedupe, drafts, previews, bots
// ============================================================

it('counts the same visitor once but still updates pages read', function () {
    $issue = MagazineIssue::factory()->published()->create();

    $headers = ['User-Agent' => 'Mozilla/5.0 (Pest fixture reader)'];

    $this->post(route('magazines.read', $issue->slug), ['page' => 1], $headers)
        ->assertOk()
        ->assertJson(['tracked' => true]);

    $this->post(route('magazines.read', $issue->slug), ['page' => 5], $headers)
        ->assertOk()
        ->assertJson(['tracked' => true]);

    $this->post(route('magazines.read', $issue->slug), ['page' => 3], $headers)
        ->assertOk()
        ->assertJson(['tracked' => true]);

    expect(MagazineRead::query()->where('magazine_issue_id', $issue->id)->count())->toBe(1);

    $row = MagazineRead::query()->where('magazine_issue_id', $issue->id)->firstOrFail();

    expect($row->max_page)->toBe(5)
        ->and($row->pages_viewed)->toBe(3);
});

it('does not count reads for draft issues', function () {
    $issue = MagazineIssue::factory()->draft()->create();

    $this->post(route('magazines.read', $issue->slug), ['page' => 2], ['User-Agent' => 'Mozilla/5.0 fake'])
        ->assertOk()
        ->assertJson(['tracked' => false, 'reason' => 'draft']);

    expect(MagazineRead::query()->count())->toBe(0);
});

it('does not count reads for signed preview requests', function () {
    $issue = MagazineIssue::factory()->published()->create();

    $url = URL::temporarySignedRoute(
        'magazines.read',
        now()->addMinutes(15),
        ['issue' => $issue->slug]
    );

    $this->post($url, ['page' => 1], ['User-Agent' => 'Mozilla/5.0 fake'])
        ->assertOk()
        ->assertJson(['tracked' => false, 'reason' => 'preview']);

    expect(MagazineRead::query()->count())->toBe(0);
});

it('does not count reads from bots', function () {
    $issue = MagazineIssue::factory()->published()->create();

    $this->post(route('magazines.read', $issue->slug), ['page' => 1], [
        'User-Agent' => 'Googlebot/2.1 (+http://www.google.com/bot.html)',
    ])
        ->assertOk()
        ->assertJson(['tracked' => false, 'reason' => 'bot']);

    expect(MagazineRead::query()->count())->toBe(0);
});

// ============================================================
// SEO: sitemap and nav
// ============================================================

it('includes published issues in the magazines sitemap and excludes drafts', function () {
    MagazineIssue::factory()->published()->create(['slug' => 'live-magazine']);
    MagazineIssue::factory()->draft()->create(['slug' => 'draft-magazine']);

    $this->get('/sitemaps/magazines.xml')
        ->assertOk()
        ->assertHeader('content-type', 'application/xml; charset=UTF-8')
        ->assertSee('live-magazine', false)
        ->assertDontSee('draft-magazine', false);
});

it('adds the magazines index to the pages sitemap only when an issue is published', function () {
    $this->get('/sitemaps/pages.xml')
        ->assertOk()
        ->assertDontSee(route('magazines.index'), false);

    // Model observer bumps the sitemap cache on save, so the next
    // request rebuilds the pages sitemap with the magazines link.
    MagazineIssue::factory()->published()->create();

    $this->get('/sitemaps/pages.xml')
        ->assertOk()
        ->assertSee(route('magazines.index'), false);
});

it('reveals the Magazine nav item once there is a published issue', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('>Magazine<', false);

    MagazineIssue::factory()->published()->create();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('>Magazine<', false);
});

// ============================================================
// Desk access gating
// ============================================================

it('shows a media partner only their own issues in the Desk resource', function () {
    $partner = User::factory()->mediaPartner()->create();
    $otherPartner = User::factory()->mediaPartner()->create();

    MagazineIssue::factory()->for($partner, 'author')->create(['title' => 'My own issue']);
    MagazineIssue::factory()->for($otherPartner, 'author')->create(['title' => 'Someone else']);

    $this->actingAs($partner);

    $query = MagazineIssueResource::getEloquentQuery();

    expect($query->pluck('title')->all())->toEqual(['My own issue']);
});

it('shows a staff user every issue in the Desk resource', function () {
    $staff = User::factory()->staff()->create();
    $partner = User::factory()->mediaPartner()->create();

    MagazineIssue::factory()->for($staff, 'author')->create(['title' => 'Staff issue']);
    MagazineIssue::factory()->for($partner, 'author')->create(['title' => 'Partner issue']);

    $this->actingAs($staff);

    $query = MagazineIssueResource::getEloquentQuery();

    expect($query->count())->toBe(2);
});

it('blocks shooters from the Desk resource entirely', function () {
    $shooter = User::factory()->create();

    $this->actingAs($shooter);

    expect(MagazineIssueResource::canViewAny())->toBeFalse();
});
