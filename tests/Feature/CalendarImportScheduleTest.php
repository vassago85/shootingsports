<?php

use App\Enums\ImportFrequency;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\ManageCalendarImports;
use App\Imports\CalendarImportRunner;
use App\Models\CalendarImportRun;
use App\Models\User;
use App\Support\CalendarImportSettings;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

function saprfImportIsDueAt(string $moment): bool
{
    test()->travelTo($moment);

    /** @var ScheduledEvent $event */
    $event = collect(app(Schedule::class)->events())
        ->first(fn (ScheduledEvent $event): bool => str_contains((string) $event->command, 'calendar:import saprf'));

    return $event->isDue(app()) && $event->filtersPass(app());
}

it('runs SAPRF on Monday mornings by default', function () {
    expect(saprfImportIsDueAt('2026-10-12 06:00:00'))->toBeTrue()
        ->and(saprfImportIsDueAt('2026-10-13 06:00:00'))->toBeFalse();
});

it('follows the frequency staff pick for a source', function () {
    CalendarImportSettings::setFrequency('saprf', ImportFrequency::Daily);
    expect(saprfImportIsDueAt('2026-10-13 06:00:00'))->toBeTrue();

    CalendarImportSettings::setFrequency('saprf', ImportFrequency::Monthly);
    expect(saprfImportIsDueAt('2026-11-01 06:00:00'))->toBeTrue()
        ->and(saprfImportIsDueAt('2026-10-12 06:00:00'))->toBeFalse();

    CalendarImportSettings::setFrequency('saprf', ImportFrequency::Off);
    expect(saprfImportIsDueAt('2026-10-12 06:00:00'))->toBeFalse();
});

it('records a failed import and exits with failure', function () {
    Http::fake(['saprf.co.za/*' => Http::response('Down for maintenance', 503)]);

    $this->artisan('calendar:import saprf')->assertFailed();

    $run = CalendarImportRun::query()->sole();

    expect($run->source)->toBe('saprf')
        ->and($run->failed())->toBeTrue();
});

it('reports failed, empty and overdue imports but not healthy or switched-off ones', function () {
    $this->travelTo('2026-10-20 09:00:00');

    CalendarImportRun::factory()->failed()->create(['source' => 'saprf']);
    CalendarImportRun::factory()->empty()->create(['source' => 'mpsa']);
    CalendarImportRun::factory()->create(['source' => 'cgpsa', 'created_at' => '2026-10-05 06:40:00']);
    CalendarImportRun::factory()->failed()->create(['source' => 'vektor']);
    CalendarImportSettings::setFrequency('vektor', ImportFrequency::Off);

    $problems = app(CalendarImportRunner::class)->problems();

    expect($problems)->toHaveCount(3)
        ->and($problems[0])->toContain('South African Precision Rifle Federation', 'failed')
        ->and($problems[1])->toContain('Mpumalanga Practical Shooting Association', 'no matches')
        ->and($problems[2])->toContain('Central Gauteng Practical Shooting Association', 'last import was 5 Oct');

    CalendarImportRun::factory()->create(['source' => 'saprf']);

    expect(app(CalendarImportRunner::class)->problems())->toHaveCount(2);
});

it('warns staff about import problems when they sign in to admin', function () {
    CalendarImportRun::factory()->failed()->create(['source' => 'saprf']);
    User::factory()->staff()->create([
        'email' => 'sara@example.test',
        'password' => Hash::make('longenoughpassword'),
    ]);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(Login::class)
        ->fillForm(['email' => 'sara@example.test', 'password' => 'longenoughpassword'])
        ->call('authenticate')
        ->assertRedirect('/admin');

    Notification::assertNotified('Calendar imports need attention');
});

it('signs staff in without a warning when imports are healthy', function () {
    CalendarImportRun::factory()->create(['source' => 'saprf']);
    User::factory()->staff()->create([
        'email' => 'sara@example.test',
        'password' => Hash::make('longenoughpassword'),
    ]);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(Login::class)
        ->fillForm(['email' => 'sara@example.test', 'password' => 'longenoughpassword'])
        ->call('authenticate')
        ->assertRedirect('/admin');

    Notification::assertNotNotified('Calendar imports need attention');
});

it('lets staff change a source frequency and run it now', function () {
    Http::fake(['saprf.co.za/*' => Http::response('<table><tbody></tbody></table>', 200)]);
    $this->actingAs(User::factory()->staff()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(ManageCalendarImports::class)
        ->fillForm(['saprf' => ImportFrequency::Daily->value])
        ->call('save')
        ->assertHasNoFormErrors()
        ->call('runImport', 'saprf')
        ->assertNotified('South African Precision Rifle Federation');

    expect(CalendarImportSettings::frequency('saprf'))->toBe(ImportFrequency::Daily)
        ->and(CalendarImportRun::query()->where('source', 'saprf')->count())->toBe(1);
});

it('keeps non-staff out of the calendar imports page', function () {
    $this->actingAs(User::factory()->create());

    $this->get(ManageCalendarImports::getUrl(panel: 'admin'))->assertForbidden();
});
