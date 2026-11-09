<?php

use App\Filament\Desk\Resources\MagazineIssues\MagazineIssueResource;
use App\Mail\MagazineDownloadLink;
use App\Models\MagazineIssue;
use App\Models\MagazineRead;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
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
        'pdf_path' => 'magazines/pdf/issue.pdf',
    ]);

    $this->get(route('magazines.show', $issue->slug))
        ->assertOk()
        ->assertSee('October 2026 Issue', false)
        ->assertSee('id="magazine-flipbook"', false)
        ->assertSee('Full screen', false)
        ->assertSee('data-track="1"', false)
        ->assertDontSee('noindex,nofollow', false);
});

it('offers an emailed PDF download when the issue allows it', function () {
    $issue = MagazineIssue::factory()->published()->create([
        'pdf_path' => 'magazines/pdf/issue.pdf',
        'pdf_download_enabled' => true,
    ]);

    $this->get(route('magazines.show', $issue->slug))
        ->assertOk()
        ->assertSee('id="magazine-flipbook"', false)
        ->assertSee('Send download link', false)
        ->assertSee('Reading on this page is free', false)
        ->assertDontSee('>Download PDF<', false);
});

it('emails a signed download link and keeps the reader open without it', function () {
    Storage::fake('media');
    Storage::disk('media')->put('magazines/pdf/issue.pdf', '%PDF-1.4');
    Mail::fake();

    $issue = MagazineIssue::factory()->published()->create([
        'title' => 'October 2026 Issue',
        'pdf_path' => 'magazines/pdf/issue.pdf',
        'pdf_download_enabled' => true,
    ]);

    $this->post(route('magazines.download-link', $issue->slug), [
        'email' => 'Reader@Example.com',
    ])->assertRedirect()
        ->assertSessionHas('download_link_sent', true);

    Mail::assertQueued(MagazineDownloadLink::class, function (MagazineDownloadLink $mail) use ($issue): bool {
        return $mail->hasTo('reader@example.com')
            && $mail->issue->is($issue)
            && str_contains($mail->downloadUrl, 'signature=');
    });

    $this->get(route('magazines.show', $issue->slug))
        ->assertOk()
        ->assertSee('id="magazine-flipbook"', false);
});

it('downloads the PDF from the emailed link and refuses an unsigned link', function () {
    Storage::fake('media');
    Storage::disk('media')->put('magazines/pdf/issue.pdf', '%PDF-1.4');

    $issue = MagazineIssue::factory()->published()->create([
        'title' => 'October 2026 Issue',
        'pdf_path' => 'magazines/pdf/issue.pdf',
        'pdf_download_enabled' => true,
    ]);

    $this->get(route('magazines.download', $issue->slug))->assertForbidden();

    $url = URL::temporarySignedRoute(
        'magazines.download',
        now()->addDays(MagazineDownloadLink::LINK_DAYS),
        ['issue' => $issue->slug],
    );

    $this->get($url)->assertOk()->assertDownload('october-2026-issue.pdf');
});

it('does not email a download link when the issue turns downloads off', function () {
    Storage::fake('media');
    Storage::disk('media')->put('magazines/pdf/issue.pdf', '%PDF-1.4');
    Mail::fake();

    $issue = MagazineIssue::factory()->published()->create([
        'pdf_path' => 'magazines/pdf/issue.pdf',
        'pdf_download_enabled' => false,
    ]);

    $this->get(route('magazines.show', $issue->slug))
        ->assertOk()
        ->assertDontSee('Send download link', false)
        ->assertSee('id="magazine-flipbook"', false);

    $this->post(route('magazines.download-link', $issue->slug), [
        'email' => 'reader@example.com',
    ])->assertNotFound();

    Mail::assertNothingQueued();
});

it('asks for a valid email before sending a download link', function () {
    Storage::fake('media');
    Storage::disk('media')->put('magazines/pdf/issue.pdf', '%PDF-1.4');

    $issue = MagazineIssue::factory()->published()->create([
        'pdf_path' => 'magazines/pdf/issue.pdf',
        'pdf_download_enabled' => true,
    ]);

    $this->from(route('magazines.show', $issue->slug))
        ->post(route('magazines.download-link', $issue->slug), [
            'email' => 'not-an-email',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('email');
});

it('hides the PDF download when the issue turns it off', function () {
    $issue = MagazineIssue::factory()->published()->create([
        'pdf_path' => 'magazines/pdf/issue.pdf',
        'pdf_download_enabled' => false,
    ]);

    $this->get(route('magazines.show', $issue->slug))
        ->assertOk()
        ->assertDontSee('Download PDF', false)
        ->assertDontSee('download the PDF', false)
        ->assertSee('id="magazine-flipbook"', false);
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

it('lists published issues on the magazines index with their publisher', function () {
    $owner = User::factory()->create(['name' => 'Range Desk', 'is_media_partner' => true]);

    MagazineIssue::factory()->published()->create([
        'user_id' => $owner->id,
        'title' => 'Published issue A',
        'publisher_name' => 'On Target Africa',
    ]);
    MagazineIssue::factory()->draft()->create(['title' => 'Draft issue B']);

    $this->get(route('magazines.index'))
        ->assertOk()
        ->assertSee('Magazines', false)
        ->assertSee('Published issue A', false)
        ->assertSee('Published by On Target Africa', false)
        ->assertDontSee('Owner · Range Desk', false)
        ->assertDontSee('Range Desk', false)
        ->assertDontSee('Draft issue B', false)
        ->assertDontSee('Targets magazine', false)
        ->assertSee('"@type":"ItemList"', false);
});

it('uses the organisation name as the public publisher and hides the uploader', function () {
    $org = Organisation::factory()->create(['name' => 'On Target Africa']);
    $uploader = User::factory()->create(['name' => 'Range Desk', 'is_media_partner' => true]);

    $issue = MagazineIssue::factory()->published()->create([
        'user_id' => $uploader->id,
        'publisher_organisation_id' => $org->id,
        'publisher_name' => 'Typed Name',
        'title' => 'Issue With Org',
    ]);

    $this->get(route('magazines.show', $issue->slug))
        ->assertOk()
        ->assertSee('Published by On Target Africa', false)
        ->assertDontSee('Typed Name', false)
        ->assertDontSee('Range Desk', false);
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

it('opens a public issue with no account', function () {
    $issue = MagazineIssue::factory()->published()->create([
        'title' => 'Open Issue',
    ]);

    $this->get(route('magazines.show', $issue->slug))
        ->assertOk()
        ->assertSee('Open Issue', false)
        ->assertDontSee('noindex,nofollow', false);
});

it('sends a guest to sign in for an issue that requires an account and brings them back', function () {
    $issue = MagazineIssue::factory()->published()->loginRequired()->create([
        'title' => 'Account Issue',
    ]);

    $this->get(route('magazines.show', $issue->slug))
        ->assertRedirect(route('login'));

    expect(session('url.intended'))->toBe(route('magazines.show', $issue->slug));
});

it('opens an account issue for a signed-in reader and keeps it out of search', function () {
    $issue = MagazineIssue::factory()->published()->loginRequired()->create([
        'title' => 'Account Issue',
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('magazines.show', $issue->slug))
        ->assertOk()
        ->assertSee('Account Issue', false)
        ->assertSee('Save to my library', false)
        ->assertSee('noindex,nofollow', false);
});

it('labels an account issue as sign-in on the public list', function () {
    MagazineIssue::factory()->published()->loginRequired()->create([
        'title' => 'Account Issue',
        'published_at' => now()->subDays(2),
    ]);
    MagazineIssue::factory()->published()->create([
        'title' => 'Open Issue',
        'published_at' => now()->subDay(),
    ]);

    $this->get(route('magazines.index'))
        ->assertOk()
        ->assertSee('Account Issue', false)
        ->assertSee('Sign in to read', false)
        ->assertSee('Open Issue', false)
        ->assertSee('Read now', false);
});

it('leaves account issues out of the magazines sitemap', function () {
    MagazineIssue::factory()->published()->loginRequired()->create([
        'slug' => 'account-magazine',
    ]);
    MagazineIssue::factory()->published()->create([
        'slug' => 'open-magazine',
    ]);

    $this->get('/sitemaps/magazines.xml')
        ->assertOk()
        ->assertSee('open-magazine', false)
        ->assertDontSee('account-magazine', false);
});

it('does not record a guest read on an account issue', function () {
    $issue = MagazineIssue::factory()->published()->loginRequired()->create();

    $this->post(route('magazines.read', $issue->slug), ['page' => 1], [
        'User-Agent' => 'Mozilla/5.0 (Pest fixture reader)',
    ])
        ->assertOk()
        ->assertJson(['tracked' => false, 'reason' => 'login']);

    expect(MagazineRead::query()->count())->toBe(0);
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
