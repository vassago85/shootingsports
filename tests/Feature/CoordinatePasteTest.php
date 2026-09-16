<?php

use App\Support\CoordinatePaste;

it('parses Google-style lat,lng paste', function () {
    $parsed = CoordinatePaste::parse('-25.952912873030343, 28.486456735843475');

    expect($parsed)->not->toBeNull()
        ->and($parsed['lat'])->toEqual(-25.952912873030343)
        ->and($parsed['lng'])->toEqual(28.486456735843475);
});

it('accepts space-separated coordinates and degree symbols', function () {
    $parsed = CoordinatePaste::parse('-26.2041° 28.0473°');

    expect($parsed)->not->toBeNull()
        ->and($parsed['lat'])->toBeLessThan(0)
        ->and($parsed['lng'])->toBeGreaterThan(0);
});

it('rejects empty, malformed, or out-of-SA coordinates', function (string $raw) {
    expect(CoordinatePaste::parse($raw))->toBeNull();
})->with([
    '',
    'not coords',
    '91, 28',
    '-25.75, 5', // west of SA box
]);
