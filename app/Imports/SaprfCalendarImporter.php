<?php

namespace App\Imports;

use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\Province;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SaprfCalendarImporter
{
    public function __construct(private CalendarImporter $importer) {}

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    public function import(): array
    {
        $html = Http::timeout(20)
            ->withUserAgent('ShootingSportsBot/1.0 (+https://shootingsports.co.za)')
            ->get('https://saprf.co.za/events?view=table')
            ->throw()
            ->body();

        $host = $this->importer->federation(
            'saprf',
            'South African Precision Rifle Federation',
            'https://saprf.co.za',
        );

        return $this->importer->persist($host, $this->parse($html));
    }

    /**
     * @return list<ImportedMatch>
     */
    public function parse(string $html): array
    {
        $dom = new DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        $rows = $xpath->query('//table//tbody/tr');
        $matches = [];

        if ($rows === false) {
            return [];
        }

        foreach ($rows as $row) {
            if (! $row instanceof DOMElement) {
                continue;
            }

            $parsed = $this->row($xpath, $row);

            if ($parsed) {
                $matches[] = $parsed;
            }
        }

        return $matches;
    }

    private function row(DOMXPath $xpath, DOMElement $row): ?ImportedMatch
    {
        $link = $xpath->query('.//a[contains(@href, "/events/")]', $row)->item(0);

        if (! $link instanceof DOMElement) {
            return null;
        }

        $href = trim($link->getAttribute('href'));

        if (! preg_match('#/events/(\d+)#', $href, $idMatch)) {
            return null;
        }

        $cells = [];

        foreach ($row->getElementsByTagName('td') as $cell) {
            $cells[] = trim(preg_replace('/\s+/', ' ', $cell->textContent) ?? '');
        }

        if (count($cells) < 5) {
            return null;
        }

        [$startsAt, $endsAt] = $this->dates($cells[0]);

        if (! $startsAt) {
            return null;
        }

        $title = trim($link->textContent);
        $discipline = $this->discipline($cells[2] ?? '');
        $level = EventLevel::tryFrom(strtolower($cells[3] ?? '')) ?? EventLevel::Club;
        $province = Province::fromCode($cells[4] ?? '')
            ?? Province::tryFrom(strtolower(str_replace(' ', '_', $cells[4] ?? '')));

        return new ImportedMatch(
            externalId: 'saprf-event-'.$idMatch[1],
            title: $title,
            entryUrl: Str::startsWith($href, 'http') ? $href : 'https://saprf.co.za'.$href,
            startsAt: $startsAt,
            endsAt: $endsAt,
            level: $level,
            status: str_contains(strtolower($cells[7] ?? $cells[6] ?? ''), 'open')
                ? EventStatus::EntriesOpen
                : EventStatus::Confirmed,
            province: $province,
            venueName: filled($cells[5] ?? null) ? $cells[5] : null,
            disciplineSlug: $discipline,
        );
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function dates(string $value): array
    {
        $value = str_replace(['–', '—'], '-', $value);

        if (preg_match('/^(\d{1,2})\s*-\s*(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})$/', $value, $match)) {
            $start = Carbon::parse($match[1].' '.$match[3].' '.$match[4], 'Africa/Johannesburg')->startOfDay();
            $end = Carbon::parse($match[2].' '.$match[3].' '.$match[4], 'Africa/Johannesburg')->startOfDay();

            return [$start, $end];
        }

        try {
            $start = Carbon::parse($value, 'Africa/Johannesburg')->startOfDay();
        } catch (\Throwable) {
            return [null, null];
        }

        return [$start, null];
    }

    private function discipline(string $label): ?string
    {
        $label = strtolower($label);

        return match (true) {
            str_contains($label, 'pr22'), str_contains($label, 'rimfire') => 'pr22-rimfire',
            str_contains($label, 'prs'), str_contains($label, 'centrefire'), str_contains($label, 'centerfire') => 'prs',
            default => 'precision-rifle',
        };
    }
}
