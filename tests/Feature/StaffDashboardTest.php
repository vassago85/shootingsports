<?php

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Enums\EventStatus;
use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Enums\ProviderCategory;
use App\Filament\Resources\Disciplines\DisciplineResource;
use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Providers\ProviderResource;
use App\Filament\Resources\Venues\VenueResource;
use App\Models\Discipline;
use App\Models\Enquiry;
use App\Models\Event;
use App\Models\Provider;
use App\Models\User;
use App\Models\Venue;

beforeEach(function () {
    $this->withoutVite();
});

it('shows the register dashboard with later staff tools still linked', function () {
    $staff = User::factory()->create(['is_staff' => true]);
    Event::factory()->create([
        'title' => 'Waiting Club Shoot',
        'status' => EventStatus::Draft,
        'confirmed_at' => null,
        'source' => ListingSource::Submission,
    ]);

    $this->actingAs($staff)
        ->get('/admin')
        ->assertOk()
        ->assertSee('What needs doing on the register')
        ->assertSee('Needs attention')
        ->assertSee('Waiting Club Shoot')
        ->assertSee('Email log')
        ->assertSee('Page pictures')
        ->assertSee('Submitted matches');
});

it('opens only new enquiries from the attention list', function () {
    $staff = User::factory()->staff()->create();
    Enquiry::query()->create([
        'type' => EnquiryType::General,
        'name' => 'New Shooter',
        'email' => 'new@example.com',
        'body' => 'Where can I shoot this weekend?',
        'status' => EnquiryStatus::New,
    ]);
    Enquiry::query()->create([
        'type' => EnquiryType::General,
        'name' => 'Closed Shooter',
        'email' => 'closed@example.com',
        'body' => 'Already answered.',
        'status' => EnquiryStatus::Closed,
    ]);

    $this->actingAs($staff)
        ->get(EnquiryResource::getUrl('index', [
            'filters' => ['status' => ['value' => EnquiryStatus::New->value]],
        ]))
        ->assertOk()
        ->assertSee('New Shooter')
        ->assertDontSee('Closed Shooter');
});

it('opens only matches missing an organiser from the attention list', function () {
    $staff = User::factory()->staff()->create();
    Event::factory()->create([
        'title' => 'No Organiser Shoot',
        'host_organisation_id' => null,
    ]);
    Event::factory()->draft()->create([
        'title' => 'Draft Orphan Shoot',
        'host_organisation_id' => null,
    ]);
    Event::factory()->create([
        'title' => 'Hosted Shoot',
    ]);

    $this->actingAs($staff)
        ->get('/admin')
        ->assertSee('missing an organiser')
        ->assertSee('missing_organiser');

    $this->actingAs($staff)
        ->get(EventResource::getUrl('index', [
            'filters' => ['missing_organiser' => ['isActive' => true]],
        ]))
        ->assertOk()
        ->assertSee('No Organiser Shoot')
        ->assertDontSee('Draft Orphan Shoot')
        ->assertDontSee('Hosted Shoot');
});

it('opens only upcoming matches missing a registration link from the attention list', function () {
    $staff = User::factory()->staff()->create();
    Event::factory()->create([
        'title' => 'No Entry Shoot',
        'entry_url' => null,
    ]);
    Event::factory()->completed()->create([
        'title' => 'Finished Shoot',
        'entry_url' => null,
    ]);
    Event::factory()->create([
        'title' => 'Linked Shoot',
        'entry_url' => 'https://example.com/enter',
    ]);

    $this->actingAs($staff)
        ->get(EventResource::getUrl('index', [
            'filters' => ['missing_registration_link' => ['isActive' => true]],
        ]))
        ->assertOk()
        ->assertSee('No Entry Shoot')
        ->assertDontSee('Finished Shoot')
        ->assertDontSee('Linked Shoot');
});

it('opens only published ranges missing GPS from the attention list', function () {
    $staff = User::factory()->staff()->create();
    Venue::factory()->create([
        'name' => 'Unmapped Range',
        'lat' => null,
        'lng' => null,
    ]);
    Venue::factory()->create([
        'name' => 'Closed Range',
        'lat' => null,
        'lng' => null,
        'status' => ListingStatus::Archived,
    ]);
    Venue::factory()->create([
        'name' => 'Mapped Range',
        'lat' => -26.1,
        'lng' => 28.0,
    ]);

    $this->actingAs($staff)
        ->get(VenueResource::getUrl('index', [
            'filters' => ['missing_gps' => ['isActive' => true]],
        ]))
        ->assertOk()
        ->assertSee('Unmapped Range')
        ->assertDontSee('Closed Range')
        ->assertDontSee('Mapped Range');
});

it('opens only published sports without a longer description from the attention list', function () {
    $staff = User::factory()->staff()->create();
    Discipline::factory()->create([
        'name' => 'Thin Sport',
        'body' => null,
        'is_published' => true,
    ]);
    Discipline::factory()->create([
        'name' => 'Hidden Sport',
        'body' => null,
        'is_published' => false,
    ]);
    Discipline::factory()->create([
        'name' => 'Full Sport',
        'body' => 'A longer description of the sport.',
        'is_published' => true,
    ]);

    $this->actingAs($staff)
        ->get(DisciplineResource::getUrl('index', [
            'filters' => ['without_longer_description' => ['isActive' => true]],
        ]))
        ->assertOk()
        ->assertSee('Thin Sport')
        ->assertDontSee('Hidden Sport')
        ->assertDontSee('Full Sport');
});

it('opens only pending industry listings from the attention list', function () {
    $staff = User::factory()->staff()->create();
    Provider::factory()->create([
        'name' => 'Pending Guns',
        'category' => ProviderCategory::Dealer,
        'status' => ListingStatus::Pending,
    ]);
    Provider::factory()->create([
        'name' => 'Live Guns',
        'category' => ProviderCategory::Dealer,
        'status' => ListingStatus::Published,
    ]);

    $this->actingAs($staff)
        ->get('/admin')
        ->assertOk()
        ->assertSee('Industry listings')
        ->assertSee('1 pending listing');

    $this->actingAs($staff)
        ->get(ProviderResource::getUrl('index', [
            'filters' => ['status' => ['value' => ListingStatus::Pending->value]],
        ]))
        ->assertOk()
        ->assertSee('Pending Guns')
        ->assertDontSee('Live Guns');
});
