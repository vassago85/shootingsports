<?php

use App\Enums\ClaimStatus;
use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Enums\ModerationStatus;
use App\Enums\SubmissionStatus;
use App\Enums\VerificationState;
use App\Filament\Pages\Inbox;
use App\Filament\Pages\ManageCalendarImports;
use App\Filament\Pages\ManageMailSettings;
use App\Filament\Pages\ManagePagePictures;
use App\Filament\Pages\Settings as AdminSettingsPage;
use App\Filament\Pages\StaffDashboard;
use App\Filament\Pages\VenueDuplicates;
use App\Filament\Pages\VerificationDashboard;
use App\Filament\Resources\AdSlots\AdSlotResource;
use App\Filament\Resources\Claims\ClaimResource;
use App\Filament\Resources\Disciplines\DisciplineResource;
use App\Filament\Resources\EmailLogs\EmailLogResource;
use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Flags\FlagResource;
use App\Filament\Resources\Media\MediaResource;
use App\Filament\Resources\Organisations\OrganisationResource;
use App\Filament\Resources\Placements\PlacementResource;
use App\Filament\Resources\Providers\ProviderResource;
use App\Filament\Resources\Submissions\SubmissionResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\Venues\VenueResource;
use App\Models\Article;
use App\Models\Claim;
use App\Models\Enquiry;
use App\Models\Media;
use App\Models\Organisation;
use App\Models\Submission;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->withoutVite();
});

// --- Nav restructure ----------------------------------------------------

it('registers only the Phase 2 top-level admin nav items for staff', function () {
    $staff = User::factory()->staff()->create();
    $this->actingAs($staff);

    $visibleRoutes = collect([
        StaffDashboard::class,
        Inbox::class,
        EventResource::class,
        OrganisationResource::class,
        VenueResource::class,
        ProviderResource::class,
        DisciplineResource::class,
        PlacementResource::class,
        UserResource::class,
        ManageCalendarImports::class,
        // Email log sits under System now, next to Imports — the
        // Chrome audit showed it was unreachable buried under Settings.
        EmailLogResource::class,
        AdminSettingsPage::class,
    ]);

    foreach ($visibleRoutes as $class) {
        expect($class::shouldRegisterNavigation())->toBeTrue(
            "{$class} should appear in the admin nav after Phase 2."
        );
    }
});

it('hides moved resources from the top-level admin nav', function () {
    $staff = User::factory()->staff()->create();
    $this->actingAs($staff);

    $hiddenRoutes = collect([
        EnquiryResource::class,
        SubmissionResource::class,
        ClaimResource::class,
        MediaResource::class,
        FlagResource::class,
        AdSlotResource::class,
        ManageMailSettings::class,
        ManagePagePictures::class,
        VerificationDashboard::class,
        VenueDuplicates::class,
    ]);

    foreach ($hiddenRoutes as $class) {
        expect($class::shouldRegisterNavigation())->toBeFalse(
            "{$class} should be hidden from the admin nav after Phase 2."
        );
    }
});

// --- Inbox --------------------------------------------------------------

it('resolves the Inbox page 200 for staff', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)
        ->get(Inbox::getUrl())
        ->assertOk()
        ->assertSee('Inbox');
});

it('denies the Inbox page to non-staff users', function () {
    // Phase 2.1 soft-redirect — Inbox is admin-only; non-staff bounce
    // to their default home instead of hitting a bare 403.
    $shooter = User::factory()->create(['is_staff' => false]);

    $this->actingAs($shooter)
        ->get(Inbox::getUrl())
        ->assertRedirect(route('my-calendar'));
});

it('reports open counts that match the sum of inbox tabs', function () {
    makePendingEnquiry();
    makePendingEnquiry();
    makePendingSubmission();
    makePendingClaim();
    makePendingMedia();
    $author = User::factory()->create();
    Article::create(['user_id' => $author->id, 'title' => 'Draft one', 'slug' => 'draft-one', 'body' => 'x', 'published_at' => null]);

    // Published article should NOT count.
    Article::create(['user_id' => $author->id, 'title' => 'Live one', 'slug' => 'live-one', 'body' => 'x', 'published_at' => now()->subDay()]);

    expect(Inbox::countOpen())->toBe(6);
});

it('switches the inbox badge colour to danger when any item is older than 3 days', function () {
    makePendingEnquiry(createdAt: now()->subDays(5));

    expect(Inbox::getNavigationBadgeColor())->toBe('danger');
});

it('keeps the inbox badge warning when nothing is stale', function () {
    makePendingEnquiry(createdAt: now()->subHour());

    expect(Inbox::getNavigationBadgeColor())->toBe('warning');
});

// --- Verification tabs on list pages ------------------------------------

it('shows the verification tabs on the Clubs list', function () {
    $staff = User::factory()->staff()->create();

    Organisation::factory()->create([
        'name' => 'Unconfirmed Club',
        'verification_state' => VerificationState::Unconfirmed,
    ]);
    Organisation::factory()->create([
        'name' => 'Verified Club',
        'verification_state' => VerificationState::Verified,
    ]);

    $this->actingAs($staff)
        ->get(OrganisationResource::getUrl('index').'?tab=unconfirmed')
        ->assertOk()
        ->assertSee('Unconfirmed Club')
        ->assertDontSee('Verified Club');
});

it('shows the verification tabs on the Ranges list', function () {
    $staff = User::factory()->staff()->create();

    Venue::factory()->create([
        'name' => 'Unconfirmed Range',
        'verification_state' => VerificationState::Unconfirmed,
    ]);
    Venue::factory()->create([
        'name' => 'Verified Range',
        'verification_state' => VerificationState::Verified,
    ]);

    $this->actingAs($staff)
        ->get(VenueResource::getUrl('index').'?tab=unconfirmed')
        ->assertOk()
        ->assertSee('Unconfirmed Range')
        ->assertDontSee('Verified Range');
});

it('shows the find duplicates header action on the Ranges list', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)
        ->get(VenueResource::getUrl('index'))
        ->assertOk()
        ->assertSee('Find duplicates');
});

// --- Settings landing ---------------------------------------------------

it('resolves the Settings landing page 200 for staff', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)
        ->get(AdminSettingsPage::getUrl())
        ->assertOk()
        ->assertSee('Settings')
        ->assertSee('Flags')
        ->assertSee('Email');
});

// --- Retired URL 301s ---------------------------------------------------

it('301-redirects /admin/verification-dashboard to the Clubs list', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)
        ->get('/admin/verification-dashboard')
        ->assertStatus(301)
        ->assertRedirect('/admin/organisations');
});

it('301-redirects /admin/venue-duplicates to the Ranges list', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)
        ->get('/admin/venue-duplicates')
        ->assertStatus(301)
        ->assertRedirect('/admin/venues');
});

// --- Today dashboard ----------------------------------------------------

it('renders the Today dashboard without the old quick-actions grid', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)
        ->get(StaffDashboard::getUrl())
        ->assertOk()
        ->assertSee('Today')
        ->assertSee('Fix these')
        ->assertSee('Next 7 days')
        ->assertDontSee('Quick actions');
});

// --- Helpers ------------------------------------------------------------

function makePendingEnquiry(?Carbon $createdAt = null): Enquiry
{
    $enquiry = Enquiry::create([
        'type' => EnquiryType::General,
        'name' => 'Pending Person',
        'email' => 'pending-'.uniqid().'@example.com',
        'subject' => 'Fixture',
        'body' => 'Fixture body',
        'status' => EnquiryStatus::New,
    ]);

    // Eloquent sets timestamps automatically on create — overriding
    // created_at there is ignored. Force the real value afterwards so
    // we can test the "stale item" rule.
    if ($createdAt !== null) {
        $enquiry->timestamps = false;
        $enquiry->created_at = $createdAt;
        $enquiry->updated_at = $createdAt;
        $enquiry->save();
        $enquiry->timestamps = true;
    }

    return $enquiry;
}

function makePendingSubmission(): Submission
{
    return Submission::create([
        'type' => 'organisation',
        'payload' => ['name' => 'Submitted Club'],
        'submitter_name' => 'Alice',
        'submitter_email' => 'alice@example.com',
        'status' => SubmissionStatus::Pending,
    ]);
}

function makePendingClaim(): Claim
{
    $org = Organisation::factory()->create();
    $user = User::factory()->create();

    return Claim::create([
        'claimable_type' => Organisation::class,
        'claimable_id' => $org->id,
        'user_id' => $user->id,
        'evidence' => 'Supporting evidence here.',
        'status' => ClaimStatus::Pending,
    ]);
}

function makePendingMedia(): Media
{
    $org = Organisation::factory()->create();

    return Media::create([
        'disk' => 'public',
        'path' => 'media/fixture.jpg',
        'mime' => 'image/jpeg',
        'bytes' => 1024,
        'attachable_type' => Organisation::class,
        'attachable_id' => $org->id,
        'role' => 'gallery',
        'moderation_status' => ModerationStatus::Pending,
    ]);
}
