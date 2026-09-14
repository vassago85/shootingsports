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

class MpsaCalendarImporter
{
    public function __construct(private CalendarImporter $importer) {}

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    public function import(): array
    {
        $html = Http::timeout(20)
            ->withUserAgent('ShootingSportsBot/1.0 (+https://shootingsports.co.za)')
            ->get('https://mpsa.net.za/calendar26.html')
            ->throw()
            ->body();

        $host = $this->importer->federation(
            'mpsa',
            'Mpumalanga Practical Shooting Association',
            'https://mpsa.net.za',
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
        $rows = $xpath->query('//table//tr');
        $matches = [];

        if ($rows === false) {
            return [];
        }

        foreach ($rows as $row) {
            if (! $row instanceof DOMElement) {
                continue;
            }

            $cells = [];

            foreach ($row->getElementsByTagName('td') as $cell) {
                $cells[] = trim(preg_replace('/\s+/', ' ', $cell->textContent) ?? '');
            }

            if (count($cells) < 3 || ! preg_match('/^\d{4}-/', $cells[0])) {
                continue;
            }

            [$startsAt, $endsAt] = $this->dates($cells[0]);

            if (! $startsAt) {
                continue;
            }

            $title = $cells[2];
            $slug = 'mpsa-'.Str::slug($title.' '.$startsAt->toDateString());

            $matches[] = new ImportedMatch(
                externalId: $slug,
                title: $title,
                entryUrl: 'https://mpsa.net.za/calendar26.html#'.$slug,
                startsAt: $startsAt,
                endsAt: $endsAt,
                level: str_contains($cells[3] ?? '', 'III') ? EventLevel::National : EventLevel::Series,
                status: $startsAt->isPast() ? EventStatus::Completed : EventStatus::Confirmed,
                province: Province::Mpumalanga,
                venueName: $cells[1] !== '' ? $cells[1] : null,
                disciplineSlug: 'ipsc-practical',
            );
        }

        return $matches;
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function dates(string $value): array
    {
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})(?:\/(\d{1,2}))?$/', trim($value), $match)) {
            $year = (int) $match[1];
            $month = (int) $match[2];
            $start = Carbon::create($year, $month, (int) $match[3], 0, 0, 0, 'Africa/Johannesburg');

            if (! $start) {
                return [null, null];
            }

            $end = null;

            if (isset($match[4]) && $match[4] !== '') {
                $endDay = (int) $match[4];
                $endMonth = $endDay < (int) $match[3] ? $month + 1 : $month;
                $endYear = $year;

                if ($endMonth > 12) {
                    $endMonth = 1;
                    $endYear++;
                }

                $end = Carbon::create($endYear, $endMonth, $endDay, 0, 0, 0, 'Africa/Johannesburg');
            }

            return [$start->startOfDay(), $end?->startOfDay()];
        }

        return [null, null];
    }
}
