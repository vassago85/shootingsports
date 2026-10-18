<?php

use App\Enums\EventPublication;
use App\Enums\EventStatus;
use App\Enums\ListingStatus;
use App\Enums\OrganisationType;
use App\Enums\OrganisationUserRole;
use App\Enums\PlacementSlot;
use App\Enums\ProviderCategory;
use App\Filament\Desk\Resources\Enquiries\EnquiryResource;
use App\Filament\Desk\Resources\EventEntries\EventEntryResource;
use App\Filament\Desk\Resources\Events\EventResource;
use App\Filament\Desk\Resources\Events\Pages\CreateEvent as DeskCreateEvent;
use App\Filament\Desk\Resources\Placements\PlacementResource;
use App\Models\Discipline;
use App\Models\Enquiry;
use App\Models\Event;
use App\Models\EventEntry;
use App\Models\Organisation;
use App\Models\Placement;
use App\Models\Provider;
use App\Models\User;
use App\Models\Venue;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->withoutVite();
    config()->set('coming-soon.enabled', false);
    Filament::setCurrentPanel('desk');
});

it('publishes a desk event immediately when the host club is trusted', function () {
    $director = User::factory()->matchDirector()->create(['is_staff' => false]);
    $host = Organisation::factory()->create([
        'status' => ListingStatus::Published,
        'type' => OrganisationType::Club,
        'trusted' => true,
    ]);
    $host->users()->attach($director->id, [
        'role' => OrganisationUserRole::MatchDirector,
        'granted_at' => now(),
    ]);
    $venue = Venue::factory()->create();
    $discipline = Discipline::factory()->create(['slug' => 'trusted-desk-disc']);

    Livewire::actingAs($director)
        ->test(DeskCreateEvent::class)
        ->assertSee('Basics')
        ->assertSee('When and where')
        ->assertSee('Extras and publish')
        ->fillForm([
            'title' => 'Trusted Club Open',
            'host_organisation_id' => $host->id,
            'discipline_ids' => [$discipline->id],
            'venue_id' => $venue->id,
            'starts_at' => now()->addMonth()->startOfHour()->toDateTimeString(),
            'all_day' => true,
            'level' => 'club',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $event = Event::query()->where('title', 'Trusted Club Open')->first();

    expect($event)->not->toBeNull()
        ->and($event->status)->toBe(EventStatus::Planned)
        ->and($event->publication)->toBe(EventPublication::Published);
});

it('hides Events from a supplier-only desk user', function () {
    $supplier = User::factory()->create(['supplier_requested_at' => now()]);
    Provider::factory()->create([
        'name' => 'Supplier Only Optics',
        'category' => ProviderCategory::Dealer,
        'status' => ListingStatus::Published,
        'claimed_by' => $supplier->id,
    ]);

    $this->actingAs($supplier);

    expect(EventResource::canViewAny())->toBeFalse()
        ->and(EventResource::shouldRegisterNavigation())->toBeFalse()
        ->and(EnquiryResource::canViewAny())->toBeTrue();
});

it('lets a supplier see impressions and clicks for their own placements only', function () {
    $supplier = User::factory()->create(['supplier_requested_at' => now()]);
    $own = Provider::factory()->create([
        'name' => 'Own Optics',
        'category' => ProviderCategory::Dealer,
        'status' => ListingStatus::Published,
        'claimed_by' => $supplier->id,
    ]);
    $other = Provider::factory()->create([
        'name' => 'Other Optics',
        'category' => ProviderCategory::Dealer,
        'status' => ListingStatus::Published,
    ]);

    Placement::query()->create([
        'provider_id' => $own->id,
        'slot' => PlacementSlot::CategorySponsor,
        'headline' => 'Own advert',
        'click_url' => 'https://example.test/own',
        'starts_on' => now()->subDay()->toDateString(),
        'ends_on' => now()->addMonth()->toDateString(),
        'impressions' => 204,
        'clicks' => 16,
        'is_active' => true,
        'rate_cents' => 0,
    ]);
    Placement::query()->create([
        'provider_id' => $other->id,
        'slot' => PlacementSlot::CategorySponsor,
        'headline' => 'Other advert',
        'click_url' => 'https://example.test/other',
        'starts_on' => now()->subDay()->toDateString(),
        'ends_on' => now()->addMonth()->toDateString(),
        'impressions' => 999,
        'clicks' => 99,
        'is_active' => true,
        'rate_cents' => 0,
    ]);

    $this->actingAs($supplier);

    expect(PlacementResource::canViewAny())->toBeTrue();

    $this->get(PlacementResource::getUrl('index'))
        ->assertOk()
        ->assertSee('Own advert')
        ->assertSee('204')
        ->assertSee('16')
        ->assertDontSee('Other advert')
        ->assertDontSee('999');
});

it('scopes desk enquiries to the supplier own listing', function () {
    $supplier = User::factory()->create(['supplier_requested_at' => now()]);
    $own = Provider::factory()->create([
        'claimed_by' => $supplier->id,
        'status' => ListingStatus::Published,
        'category' => ProviderCategory::Dealer,
    ]);
    $other = Provider::factory()->create([
        'status' => ListingStatus::Published,
        'category' => ProviderCategory::Dealer,
    ]);

    Enquiry::query()->create([
        'type' => 'listing',
        'name' => 'Own Buyer',
        'email' => 'own@example.test',
        'subject' => 'About your glass',
        'body' => 'Do you stock 6-24x?',
        'about_type' => Provider::class,
        'about_id' => $own->id,
        'status' => 'new',
    ]);
    Enquiry::query()->create([
        'type' => 'listing',
        'name' => 'Other Buyer',
        'email' => 'other@example.test',
        'subject' => 'Secret enquiry',
        'body' => 'Should stay hidden',
        'about_type' => Provider::class,
        'about_id' => $other->id,
        'status' => 'new',
    ]);

    $this->actingAs($supplier)
        ->get(EnquiryResource::getUrl('index'))
        ->assertOk()
        ->assertSee('Own Buyer')
        ->assertSee('About your glass')
        ->assertDontSee('Other Buyer')
        ->assertDontSee('Secret enquiry');
});

it('shows Entries in the desk nav only when a managed event takes on-site entries', function () {
    $director = User::factory()->matchDirector()->create(['is_staff' => false]);
    $host = Organisation::factory()->create(['status' => ListingStatus::Published]);
    $host->users()->attach($director->id, [
        'role' => OrganisationUserRole::MatchDirector,
        'granted_at' => now(),
    ]);

    $this->actingAs($director);
    expect(EventEntryResource::canViewAny())->toBeFalse();

    $event = Event::factory()->create([
        'host_organisation_id' => $host->id,
        'accepts_platform_entries' => true,
        'status' => EventStatus::EntriesOpen,
        'publication' => EventPublication::Published,
    ]);

    expect(EventEntryResource::canViewAny())->toBeTrue();

    $shooter = User::factory()->create();
    EventEntry::query()->create([
        'event_id' => $event->id,
        'user_id' => $shooter->id,
        'status' => 'entered',
        'payment_status' => 'external',
    ]);

    $this->get(EventEntryResource::getUrl('index'))
        ->assertOk()
        ->assertSee($shooter->name);
});
