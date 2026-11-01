<?php

use App\Models\Event;
use Illuminate\Support\Carbon;

afterEach(function (): void {
    Carbon::setTestNow();
});

it('keeps a match that started on Friday on the Sunday home rail', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-01 10:00:00', 'Africa/Johannesburg'));

    Event::factory()->confirmed()->create([
        'title' => 'Spans The Weekend',
        'starts_at' => Carbon::parse('2026-10-30 08:00:00', 'Africa/Johannesburg'),
        'ends_at' => Carbon::parse('2026-11-01 18:00:00', 'Africa/Johannesburg'),
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('This weekend')
        ->assertSee('Spans The Weekend');
});
