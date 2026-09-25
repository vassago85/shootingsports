<?php

use App\Enums\ListingStatus;
use App\Enums\OrganisationType;
use App\Enums\OrganisationUserRole;
use App\Enums\ProviderCategory;
use App\Filament\Desk\Resources\Events\Pages\EditEvent as DeskEditEvent;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Provider;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('shows published supplier partners on the match page', function () {
    $event = Event::factory()->create(['title' => 'Royal Flush']);
    $partner = Provider::factory()->create([
        'name' => 'Feather Fur and Target',
        'category' => ProviderCategory::Ammunition,
        'status' => ListingStatus::Published,
    ]);
    $pending = Provider::factory()->create([
        'name' => 'Pending Sponsor Co',
        'category' => ProviderCategory::Optics,
        'status' => ListingStatus::Pending,
    ]);
    $distributor = Provider::factory()->create([
        'name' => 'Hidden Distributor',
        'category' => ProviderCategory::Distributor,
        'status' => ListingStatus::Published,
    ]);

    $event->partners()->attach([$partner->id, $pending->id, $distributor->id]);

    $this->get(route('matches.show', $event))
        ->assertOk()
        ->assertSee('match-partners', false)
        ->assertSee('Feather Fur and Target', false)
        ->assertSee(route('suppliers.show', $partner), false)
        ->assertSee('"sponsor"', false)
        ->assertDontSee('Pending Sponsor Co', false)
        ->assertDontSee('Hidden Distributor', false);
});

it('omits the partners block when a match has none', function () {
    $event = Event::factory()->create();

    $this->get(route('matches.show', $event))
        ->assertOk()
        ->assertDontSee('match-partners', false);
});

it('lets staff attach a supplier partner from the event form', function () {
    $staff = User::factory()->create(['is_staff' => true]);
    $event = Event::factory()->create();
    $discipline = Discipline::factory()->create();
    $event->attachDiscipline($discipline, true);
    $partner = Provider::factory()->create([
        'name' => 'Feather Fur and Target',
        'category' => ProviderCategory::Ammunition,
        'status' => ListingStatus::Published,
    ]);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::actingAs($staff)
        ->test(EditEvent::class, ['record' => $event->getRouteKey()])
        ->fillForm([
            'partners' => [$partner->id],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($event->fresh()->partners()->pluck('providers.id')->all())->toBe([$partner->id]);
});

it('lets a match director attach a supplier partner from the desk', function () {
    $director = User::factory()->create(['is_staff' => false]);
    $host = Organisation::factory()->create([
        'type' => OrganisationType::Club,
        'status' => ListingStatus::Published,
    ]);
    $host->users()->attach($director->id, [
        'role' => OrganisationUserRole::MatchDirector,
        'granted_at' => now(),
    ]);
    $event = Event::factory()->create(['host_organisation_id' => $host->id]);
    $discipline = Discipline::factory()->create();
    $event->attachDiscipline($discipline, true);
    $partner = Provider::factory()->create([
        'name' => 'Feather Fur and Target',
        'category' => ProviderCategory::Ammunition,
        'status' => ListingStatus::Published,
    ]);

    Filament::setCurrentPanel(Filament::getPanel('desk'));

    Livewire::actingAs($director)
        ->test(DeskEditEvent::class, ['record' => $event->getRouteKey()])
        ->fillForm([
            'partners' => [$partner->id],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($event->fresh()->partners()->pluck('providers.id')->all())->toBe([$partner->id]);
});
