<?php

use App\Livewire\CalendarFilter;
use App\Models\Event;
use App\Queries\PublicEventQuery;
use App\Support\ThisWeekend;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
});

it('resolves Mon–Fri to the upcoming Saturday–Sunday window', function () {
    Carbon::setTestNow(Carbon::create(2026, 9, 16, 10, 0, 0, 'Africa/Johannesburg')); // Wednesday

    [$from, $to] = ThisWeekend::range();

    expect($from->toDateString())->toBe('2026-09-19')
        ->and($to->toDateString())->toBe('2026-09-20')
        ->and(ThisWeekend::label())->toBe('19–20 Sep');

    Carbon::setTestNow();
});

it('keeps Saturday morning inside the current weekend', function () {
    Carbon::setTestNow(Carbon::create(2026, 9, 19, 9, 0, 0, 'Africa/Johannesburg'));

    [$from, $to] = ThisWeekend::range();

    expect($from->toDateString())->toBe('2026-09-19')
        ->and($to->toDateString())->toBe('2026-09-20');

    Carbon::setTestNow();
});

it('treats Sunday as this weekend only', function () {
    Carbon::setTestNow(Carbon::create(2026, 9, 20, 11, 0, 0, 'Africa/Johannesburg'));

    [$from, $to] = ThisWeekend::range();

    expect($from->toDateString())->toBe('2026-09-20')
        ->and($to->toDateString())->toBe('2026-09-20')
        ->and(ThisWeekend::label())->toBe('20 Sep');

    Carbon::setTestNow();
});

it('filters PublicEventQuery to the weekend window when weekend=true', function () {
    Carbon::setTestNow(Carbon::create(2026, 9, 16, 10, 0, 0, 'Africa/Johannesburg'));

    Event::factory()->confirmed()->create([
        'title' => 'Weekend Gong',
        'starts_at' => Carbon::create(2026, 9, 19, 8, 0, 0, 'Africa/Johannesburg'),
    ]);
    Event::factory()->confirmed()->create([
        'title' => 'Next Week PRS',
        'starts_at' => Carbon::create(2026, 9, 26, 8, 0, 0, 'Africa/Johannesburg'),
    ]);

    $titles = (new PublicEventQuery(weekend: true))->get()->pluck('title');

    expect($titles)->toContain('Weekend Gong')
        ->not->toContain('Next Week PRS');

    Carbon::setTestNow();
});

it('honours ?weekend=1 on the calendar page and Livewire filter', function () {
    Carbon::setTestNow(Carbon::create(2026, 9, 16, 10, 0, 0, 'Africa/Johannesburg'));

    Event::factory()->confirmed()->create([
        'title' => 'Saturday Steel',
        'starts_at' => Carbon::create(2026, 9, 19, 8, 0, 0, 'Africa/Johannesburg'),
    ]);
    Event::factory()->confirmed()->create([
        'title' => 'Midweek Practice',
        'starts_at' => Carbon::create(2026, 9, 23, 8, 0, 0, 'Africa/Johannesburg'),
    ]);

    $this->get(route('calendar', ['weekend' => 1]))
        ->assertOk()
        ->assertSee('This weekend', false)
        ->assertSee('Saturday Steel', false)
        ->assertDontSee('Midweek Practice', false);

    Livewire::test(CalendarFilter::class, ['weekend' => true])
        ->assertSee('Saturday Steel')
        ->assertDontSee('Midweek Practice');

    Carbon::setTestNow();
});

it('shows a useful empty state when nothing is listed this weekend', function () {
    Carbon::setTestNow(Carbon::create(2026, 9, 16, 10, 0, 0, 'Africa/Johannesburg'));

    Livewire::test(CalendarFilter::class, ['weekend' => true])
        ->assertSee('Nothing listed for this weekend')
        ->assertSee('19–20 Sep')
        ->assertSee('Show all upcoming');

    Carbon::setTestNow();
});
