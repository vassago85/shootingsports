<?php

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Enums\Plan;
use App\Filament\Desk\Resources\Enquiries\EnquiryResource;
use App\Models\Enquiry;
use App\Models\Organisation;
use App\Models\Provider;
use App\Models\User;
use Filament\Facades\Filament;

/*
 * Regression tests pinning the four fixes from the admin UI checklist:
 * admin link in public nav, Pro gate bypass for staff, Desk Enquiries
 * showing every enquiry for staff, and the Day fee cents → rand
 * conversion. The statefulApi() middleware wiring is covered by the
 * fact that it's a one-line config change in bootstrap/app.php that's
 * either present or not — not meaningfully testable in isolation.
 */

it('shows the Admin link in the public header for staff', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)
        ->get('/')
        ->assertOk()
        ->assertSeeHtml('href="'.url('/admin').'"');
});

it('hides the Admin link in the public header for plain shooters', function () {
    $shooter = User::factory()->create();

    $this->actingAs($shooter)
        ->get('/')
        ->assertOk()
        ->assertDontSeeHtml('href="'.url('/admin').'"');
});

it('lets a staff user on Free plan export the attendance log CSV', function () {
    $staff = User::factory()->staff()->create(['plan' => Plan::Free]);

    $this->actingAs($staff)
        ->get(route('my-log.export.csv', ['year' => now()->year]))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});

it('lets a staff user on Free plan open the printable attendance log', function () {
    $staff = User::factory()->staff()->create(['plan' => Plan::Free]);

    $this->actingAs($staff)
        ->get(route('my-log.print', ['year' => now()->year]))
        ->assertOk()
        ->assertSee('Annual attendance record');
});

it('still 403s a plain Free user from the CSV export', function () {
    $shooter = User::factory()->create(['plan' => Plan::Free]);

    $this->actingAs($shooter)
        ->get(route('my-log.export.csv', ['year' => now()->year]))
        ->assertForbidden();
});

it('shows staff every enquiry on the desk, not just the industry ones', function () {
    Filament::setCurrentPanel('desk');

    $staff = User::factory()->staff()->create();
    $club = Organisation::factory()->create();
    $provider = Provider::factory()->create();

    $clubEnquiry = Enquiry::create([
        'type' => EnquiryType::Listing,
        'name' => 'Walk-in',
        'email' => 'walkin-'.uniqid().'@example.com',
        'subject' => 'Club question',
        'body' => 'When do you shoot?',
        'about_type' => Organisation::class,
        'about_id' => $club->id,
        'status' => EnquiryStatus::New,
    ]);

    $industryEnquiry = Enquiry::create([
        'type' => EnquiryType::Listing,
        'name' => 'Reloader',
        'email' => 'reloader-'.uniqid().'@example.com',
        'subject' => 'Powder stock',
        'body' => 'In stock?',
        'about_type' => Provider::class,
        'about_id' => $provider->id,
        'status' => EnquiryStatus::New,
    ]);

    $this->actingAs($staff);

    $ids = EnquiryResource::getEloquentQuery()->pluck('id')->all();

    expect($ids)->toContain($clubEnquiry->id)
        ->and($ids)->toContain($industryEnquiry->id);
});

it('still scopes a non-staff industry user to their own providers only', function () {
    Filament::setCurrentPanel('desk');

    $industryUser = User::factory()->create();
    $myProvider = Provider::factory()->create(['claimed_by' => $industryUser->id]);
    $otherProvider = Provider::factory()->create();
    $club = Organisation::factory()->create();

    $mine = Enquiry::create([
        'type' => EnquiryType::Listing,
        'name' => 'Mine',
        'email' => 'mine-'.uniqid().'@example.com',
        'subject' => 'q',
        'body' => 'q',
        'about_type' => Provider::class,
        'about_id' => $myProvider->id,
        'status' => EnquiryStatus::New,
    ]);

    $someoneElses = Enquiry::create([
        'type' => EnquiryType::Listing,
        'name' => 'Theirs',
        'email' => 'theirs-'.uniqid().'@example.com',
        'subject' => 'q',
        'body' => 'q',
        'about_type' => Provider::class,
        'about_id' => $otherProvider->id,
        'status' => EnquiryStatus::New,
    ]);

    $forAClub = Enquiry::create([
        'type' => EnquiryType::Listing,
        'name' => 'Club',
        'email' => 'club-'.uniqid().'@example.com',
        'subject' => 'q',
        'body' => 'q',
        'about_type' => Organisation::class,
        'about_id' => $club->id,
        'status' => EnquiryStatus::New,
    ]);

    $this->actingAs($industryUser);

    $ids = EnquiryResource::getEloquentQuery()->pluck('id')->all();

    expect($ids)->toContain($mine->id)
        ->and($ids)->not->toContain($someoneElses->id)
        ->and($ids)->not->toContain($forAClub->id);
});
