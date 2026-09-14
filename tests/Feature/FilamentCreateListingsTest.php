<?php

use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\FlagFamily;
use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Enums\OrganisationType;
use App\Enums\OrganisationUserRole;
use App\Enums\VerificationState;
use App\Filament\Desk\Resources\Events\Pages\CreateEvent as DeskCreateEvent;
use App\Filament\Desk\Resources\Organisations\Pages\CreateOrganisation as DeskCreateOrganisation;
use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Filament\Resources\Organisations\Pages\CreateOrganisation as AdminCreateOrganisation;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Flag;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Venue;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('auto-fills organisation slug from name on admin create', function () {
    $staff = User::factory()->create(['is_staff' => true]);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::actingAs($staff)
        ->test(AdminCreateOrganisation::class)
        ->fillForm([
            'name' => 'Royal Flush Steel Challenge',
        ])
        ->assertFormSet([
            'slug' => 'royal-flush-steel-challenge',
        ]);
});

it('creates a club from admin', function () {
    $staff = User::factory()->create(['is_staff' => true]);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::actingAs($staff)
        ->test(AdminCreateOrganisation::class)
        ->fillForm([
            'name' => 'Pretoria Precision Rifle Club',
            'type' => OrganisationType::Club->value,
            'province' => 'gauteng',
            'town' => 'Pretoria',
            'status' => ListingStatus::Published->value,
            'verification_state' => VerificationState::Unconfirmed->value,
            'source' => ListingSource::Staff->value,
            'accredited' => false,
            'visitors_welcome' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $club = Organisation::query()->where('name', 'Pretoria Precision Rifle Club')->first();

    expect($club)->not->toBeNull()
        ->and($club->type)->toBe(OrganisationType::Club)
        ->and($club->slug)->toBe('pretoria-precision-rifle-club');
});

it('creates an association from admin', function () {
    $staff = User::factory()->create(['is_staff' => true]);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::actingAs($staff)
        ->test(AdminCreateOrganisation::class)
        ->fillForm([
            'name' => 'Highveld Shooting Association',
            'type' => OrganisationType::Association->value,
            'status' => ListingStatus::Published->value,
            'verification_state' => VerificationState::Unconfirmed->value,
            'source' => ListingSource::Staff->value,
            'accredited' => false,
            'visitors_welcome' => false,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $association = Organisation::query()->where('name', 'Highveld Shooting Association')->first();

    expect($association)->not->toBeNull()
        ->and($association->type)->toBe(OrganisationType::Association)
        ->and($association->slug)->toBe('highveld-shooting-association');
});

it('creates a series from admin', function () {
    $staff = User::factory()->create(['is_staff' => true]);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::actingAs($staff)
        ->test(AdminCreateOrganisation::class)
        ->fillForm([
            'name' => 'Royal Flush Series',
            'type' => OrganisationType::Series->value,
            'status' => ListingStatus::Published->value,
            'verification_state' => VerificationState::Unconfirmed->value,
            'source' => ListingSource::Staff->value,
            'accredited' => false,
            'visitors_welcome' => false,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Organisation::query()->where('type', OrganisationType::Series)->where('slug', 'royal-flush-series')->exists())->toBeTrue();
});

it('lays out the admin event form in tabs', function () {
    $staff = User::factory()->create(['is_staff' => true]);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::actingAs($staff)
        ->test(CreateEvent::class)
        ->assertSee('Details')
        ->assertSee('Schedule')
        ->assertSee('Classification & Format')
        ->assertSee('Entry & Fees')
        ->assertSee('Media & Results')
        ->assertSee('Who this match is for');
});

it('creates an event from admin', function () {
    $staff = User::factory()->create(['is_staff' => true]);
    $host = Organisation::factory()->create([
        'status' => ListingStatus::Published,
        'type' => OrganisationType::Club,
    ]);
    $venue = Venue::factory()->create(['status' => ListingStatus::Published]);
    $discipline = Discipline::factory()->create(['name' => 'PRS', 'slug' => 'prs-admin-create']);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::actingAs($staff)
        ->test(CreateEvent::class)
        ->fillForm([
            'title' => 'PPRC Club Match September',
            'host_organisation_id' => $host->id,
            'venue_id' => $venue->id,
            'discipline_ids' => [$discipline->id],
            'starts_at' => now()->addWeeks(2)->startOfHour()->toDateTimeString(),
            'all_day' => true,
            'level' => EventLevel::Series->value,
            'status' => EventStatus::Confirmed->value,
            'source' => ListingSource::Staff->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $event = Event::query()->where('title', 'PPRC Club Match September')->first();

    expect($event)->not->toBeNull()
        ->and($event->slug)->toBe('pprc-club-match-september')
        ->and($event->level)->toBe(EventLevel::Series)
        ->and($event->host_organisation_id)->toBe($host->id)
        ->and($event->primaryDiscipline()?->id)->toBe($discipline->id);
});

it('auto-fills event slug from title on admin create', function () {
    $staff = User::factory()->create(['is_staff' => true]);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::actingAs($staff)
        ->test(CreateEvent::class)
        ->fillForm([
            'title' => 'Winter League Round 3',
        ])
        ->assertFormSet([
            'slug' => 'winter-league-round-3',
        ]);
});

it('creates a match from the desk with a discipline', function () {
    $director = User::factory()->create(['is_staff' => false]);
    $host = Organisation::factory()->create([
        'status' => ListingStatus::Published,
        'type' => OrganisationType::Club,
    ]);
    $host->users()->attach($director->id, [
        'role' => OrganisationUserRole::MatchDirector,
        'granted_at' => now(),
    ]);
    $discipline = Discipline::factory()->create(['name' => 'Sporting Clays', 'slug' => 'sporting-clays-desk']);
    Filament::setCurrentPanel(Filament::getPanel('desk'));

    Livewire::actingAs($director)
        ->test(DeskCreateEvent::class)
        ->fillForm([
            'title' => 'Valley Sporting Open',
            'host_organisation_id' => $host->id,
            'discipline_ids' => [$discipline->id],
            'starts_at' => now()->addMonth()->startOfHour()->toDateTimeString(),
            'all_day' => true,
            'level' => EventLevel::Club->value,
            'status' => EventStatus::Confirmed->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $event = Event::query()->where('title', 'Valley Sporting Open')->first();

    expect($event)->not->toBeNull()
        ->and($event->primaryDiscipline()?->id)->toBe($discipline->id);
});

it('creates a club from the match director desk', function () {
    $director = User::factory()->create(['is_staff' => false]);
    Filament::setCurrentPanel(Filament::getPanel('desk'));

    Livewire::actingAs($director)
        ->test(DeskCreateOrganisation::class)
        ->fillForm([
            'name' => 'Desk Created Rifle Club',
            'type' => OrganisationType::Club->value,
            'province' => 'gauteng',
            'town' => 'Centurion',
            'visitors_welcome' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $club = Organisation::query()->where('name', 'Desk Created Rifle Club')->first();

    expect($club)->not->toBeNull()
        ->and($club->status)->toBe(ListingStatus::Pending)
        ->and($club->slug)->toBe('desk-created-rifle-club')
        ->and($club->users()->where('users.id', $director->id)->wherePivot('role', OrganisationUserRole::MatchDirector->value)->exists())->toBeTrue();
});

it('saves new-shooter-friendly from admin and desk match forms', function () {
    $flag = Flag::query()->create([
        'slug' => 'new-shooter-friendly',
        'name' => 'New shooter friendly',
        'definition' => 'A first-timer can shoot without being a burden on the squad.',
        'family' => FlagFamily::Access,
        'sort_order' => 10,
        'is_filterable' => true,
    ]);
    $discipline = Discipline::factory()->create(['slug' => 'flag-discipline']);

    $staff = User::factory()->create(['is_staff' => true]);
    $adminHost = Organisation::factory()->create(['status' => ListingStatus::Published]);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::actingAs($staff)
        ->test(CreateEvent::class)
        ->fillForm([
            'title' => 'Admin Novice Match',
            'host_organisation_id' => $adminHost->id,
            'discipline_ids' => [$discipline->id],
            'starts_at' => now()->addWeeks(2)->startOfHour()->toDateTimeString(),
            'all_day' => true,
            'level' => EventLevel::Club->value,
            'status' => EventStatus::Confirmed->value,
            'source' => ListingSource::Staff->value,
            'flag_ids' => [$flag->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $adminEvent = Event::query()->where('title', 'Admin Novice Match')->first();

    expect($adminEvent?->flags()->where('slug', 'new-shooter-friendly')->exists())->toBeTrue();

    $director = User::factory()->create(['is_staff' => false]);
    $deskHost = Organisation::factory()->create(['status' => ListingStatus::Published]);
    $deskHost->users()->attach($director->id, [
        'role' => OrganisationUserRole::MatchDirector,
        'granted_at' => now(),
    ]);
    Filament::setCurrentPanel(Filament::getPanel('desk'));

    Livewire::actingAs($director)
        ->test(DeskCreateEvent::class)
        ->assertSee('New shooter friendly')
        ->fillForm([
            'title' => 'Desk Novice Match',
            'host_organisation_id' => $deskHost->id,
            'discipline_ids' => [$discipline->id],
            'starts_at' => now()->addMonth()->startOfHour()->toDateTimeString(),
            'all_day' => true,
            'level' => EventLevel::Club->value,
            'status' => EventStatus::Confirmed->value,
            'flag_ids' => [$flag->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Event::query()->where('title', 'Desk Novice Match')->first()?->flags()->where('slug', 'new-shooter-friendly')->exists())->toBeTrue();
});
