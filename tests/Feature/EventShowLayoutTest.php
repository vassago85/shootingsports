<?php

use App\Enums\Province;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Venue;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->withoutVite();
    Cache::flush();
});

it('prints a discipline once in the province subtitle', function (string $name) {
    $discipline = Discipline::factory()->create(['name' => $name]);
    $venue = Venue::factory()->create([
        'province' => Province::WesternCape,
        'lat' => null,
        'lng' => null,
        'address' => null,
    ]);
    $event = Event::factory()->create([
        'venue_id' => $venue->id,
        'description' => 'Final.',
    ]);
    $event->syncDisciplines([$discipline->id], $discipline->id);

    $html = $this->get(route('events.show', $event->slug))->assertOk()->getContent();

    expect(substr_count($html, $name.' in Western Cape'))->toBe(1)
        ->and(substr_count($html, '>'.$name.'</a>'))->toBe(0);
})->with(['ELR', 'IPSC Handgun']);

it('stacks a short description when the venue has no address or map', function () {
    $event = Event::factory()->create([
        'venue_id' => null,
        'description' => 'One line.',
    ]);

    $html = $this->get(route('events.show', $event->slug))->assertOk()->getContent();

    expect($html)->toContain('is-stacked')
        ->and($html)->toContain('One line.')
        ->and($html)->not->toContain('Match info')
        ->and($html)->not->toContain('openstreetmap.org')
        ->and($html)->not->toContain('coming up');
});

it('keeps a short description beside the map when the venue has coordinates', function () {
    $venue = Venue::factory()->create([
        'name' => 'Layout Range',
        'address' => 'Off the R318',
        'lat' => -33.42915,
        'lng' => 19.83748,
        'province' => Province::WesternCape,
    ]);
    $discipline = Discipline::factory()->create([
        'name' => 'ELR',
        'typical_distances' => '1 000–3 200 m',
    ]);
    $event = Event::factory()->create([
        'venue_id' => $venue->id,
        'title' => 'Short mapped match',
        'description' => 'Prizegiving at the clubhouse.',
    ]);
    $related = Event::factory()->create([
        'title' => 'Related one mile',
        'venue_id' => $venue->id,
    ]);
    $event->syncDisciplines([$discipline->id], $discipline->id);
    $related->syncDisciplines([$discipline->id], $discipline->id);

    $html = $this->get(route('events.show', $event->slug))->assertOk()->getContent();

    expect($html)->toContain('match-layout-media')
        ->and($html)->not->toContain('is-stacked')
        ->and($html)->toContain('Match info')
        ->and($html)->toContain('Off the R318')
        ->and($html)->toContain('1 000–3 200 m')
        ->and($html)->toContain('maps.google.com/maps?q=')
        ->and($html)->not->toContain('openstreetmap.org')
        ->and($html)->toContain('match-mini')
        ->and($html)->toContain('Related one mile')
        ->and($html)->toContain('View match')
        ->and($html)->toContain(route('calendar', ['discipline' => $discipline->slug]))
        ->and($html)->not->toContain('dope-grid');

    expect(strpos($html, 'match-layout'))->toBeLessThan(strpos($html, 'match-sponsor'))
        ->and(strpos($html, 'match-sponsor'))->toBeLessThan(strpos($html, 'match-related'));
});

it('uses the card grid when four related events follow a long description', function () {
    $discipline = Discipline::factory()->create([
        'name' => 'PR22',
        'typical_distances' => '50–300 m',
    ]);
    $event = Event::factory()->create([
        'venue_id' => null,
        'description' => str_repeat('Bring ear protection and a zeroed rifle. ', 8),
    ]);
    $event->syncDisciplines([$discipline->id], $discipline->id);

    foreach (range(1, 4) as $index) {
        $related = Event::factory()->create([
            'title' => 'Related match '.$index,
            'venue_id' => null,
        ]);
        $related->syncDisciplines([$discipline->id], $discipline->id);
    }

    $html = $this->get(route('events.show', $event->slug))->assertOk()->getContent();

    expect($html)->not->toContain('is-stacked')
        ->and($html)->toContain('Match info')
        ->and($html)->toContain('dope-grid')
        ->and($html)->toContain('Related match 1')
        ->and($html)->toContain('View all PR22 events')
        ->and($html)->not->toContain('match-mini')
        ->and($html)->not->toContain('openstreetmap.org');
});
