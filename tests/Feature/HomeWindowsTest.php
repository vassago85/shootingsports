<?php

use App\Support\HomeWindows;
use App\Support\ThisWeekend;
use Illuminate\Support\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

it('weekend() mirrors ThisWeekend::range()', function () {
    Carbon::setTestNow(Carbon::create(2026, 9, 16, 10, 0, 0, 'Africa/Johannesburg')); // Wednesday

    [$fromWindow, $toWindow] = HomeWindows::weekend();
    [$fromRef, $toRef] = ThisWeekend::range();

    expect($fromWindow->equalTo($fromRef))->toBeTrue()
        ->and($toWindow->equalTo($toRef))->toBeTrue();
});

it('next30Days() returns [now, now + 30 days] in the app timezone', function () {
    Carbon::setTestNow(Carbon::create(2026, 10, 15, 12, 0, 0, 'Africa/Johannesburg'));

    [$from, $to] = HomeWindows::next30Days();

    expect($from->toDateString())->toBe('2026-10-15')
        ->and($to->toDateString())->toBe('2026-11-14')
        ->and($from->diffInDays($to, absolute: true))->toBe(30.0)
        ->and($from->timezoneName)->toBe('Africa/Johannesburg')
        ->and($to->timezoneName)->toBe('Africa/Johannesburg');
});

it('next30Days() accepts an explicit anchor without mutating it', function () {
    $anchor = Carbon::create(2026, 1, 1, 0, 0, 0, 'Africa/Johannesburg');

    [$from, $to] = HomeWindows::next30Days($anchor);

    expect($from->toDateString())->toBe('2026-01-01')
        ->and($to->toDateString())->toBe('2026-01-31')
        ->and($anchor->toDateString())->toBe('2026-01-01'); // untouched
});
