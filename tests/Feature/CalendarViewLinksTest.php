<?php

use App\Livewire\CalendarFilter;
use App\Livewire\CalendarMonth;
use App\Support\CalendarLinks;
use Livewire\Livewire;

/**
 * @return array<string, array<string, mixed>>
 */
function hrefQueries(string $html): array
{
    preg_match_all('/href="([^"]+)"/', $html, $matches);
    $queries = [];

    foreach ($matches[1] as $href) {
        $href = html_entity_decode($href);
        $query = [];
        parse_str((string) parse_url($href, PHP_URL_QUERY), $query);
        $queries[$href] = $query;
    }

    return $queries;
}

function assertCleanViewLinks(string $html): void
{
    foreach (hrefQueries($html) as $query) {
        $keys = implode(' ', array_keys($query));

        expect($keys)->not->toContain('_token')
            ->and($keys)->not->toContain('components')
            ->and($keys)->not->toContain('snapshot')
            ->and($keys)->not->toContain('calls')
            ->and($keys)->not->toContain('checksum');
    }
}

it('keeps view links on the province filter after setProvinceOnly when the request is a livewire update', function () {
    $html = Livewire::test(CalendarFilter::class)
        ->call('setProvinceOnly', 'gauteng')
        ->html();

    assertCleanViewLinks($html);

    $map = collect(hrefQueries($html))->first(fn (array $query, string $href): bool => str_contains($href, '/map'));

    expect($map['province'] ?? null)->toBe('gauteng')
        ->and($html)->toContain('role="status"');
});

it('drops filters from view links after clear all and keeps a weekend flag when set', function () {
    $cleared = Livewire::test(CalendarFilter::class)
        ->call('setProvinceOnly', 'gauteng')
        ->call('clearAll')
        ->html();

    assertCleanViewLinks($cleared);

    $clearedMap = collect(hrefQueries($cleared))->first(fn (array $query, string $href): bool => str_contains($href, '/map'));

    expect($clearedMap)->not->toHaveKey('province');

    $weekend = Livewire::test(CalendarFilter::class)
        ->call('toggleWeekend')
        ->html();

    $weekendMap = collect(hrefQueries($weekend))->first(fn (array $query, string $href): bool => str_contains($href, '/map'));

    expect($weekendMap['weekend'] ?? null)->toBe('1');
});

it('keeps the month on the month link and off the list and map links', function () {
    $html = Livewire::test(CalendarMonth::class)
        ->set('month', '2026-12')
        ->set('province', 'gauteng')
        ->html();

    assertCleanViewLinks($html);

    $queries = hrefQueries($html);
    $month = collect($queries)->first(fn (array $query, string $href): bool => str_contains($href, '/calendar/month'));
    $list = collect($queries)->first(fn (array $query, string $href): bool => str_contains($href, '/calendar') && ! str_contains($href, '/calendar/month'));
    $map = collect($queries)->first(fn (array $query, string $href): bool => str_contains($href, '/map'));

    expect($month['month'] ?? null)->toBe('2026-12')
        ->and($month['province'] ?? null)->toBe('gauteng')
        ->and($list)->not->toHaveKey('month')
        ->and($map)->not->toHaveKey('month')
        ->and($map['province'] ?? null)->toBe('gauteng');
});

it('rejects transport-shaped and out-of-range filter values', function () {
    $state = new class
    {
        public string $family = 'not-a-family';

        public string $province = 'gauteng,<script>';

        public string $lat = '999';

        public string $lng = '28.2';

        public string $radius = '9000';

        public bool $weekend = true;

        public string $discipline = 'pr22-rimfire';
    };

    expect(CalendarLinks::parameters($state, false))->toBe([
        'weekend' => '1',
        'discipline' => 'pr22-rimfire',
        'province' => 'gauteng',
    ]);
});
