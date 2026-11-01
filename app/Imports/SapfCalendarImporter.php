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

/**
 * SAPF (South African Pistol Federation / "SAPA" at samarksman.co.za).
 *
 * The federation publishes its season on the home page as a plain
 * HTML table with columns: event name / date / venue / entry form
 * link / results link. Everything is bullseye-style pistol (ISSF,
 * NPA, PPC), so matches are tagged with the Target Pistol discipline.
 *
 * Date strings are free text in two shapes:
 *   "19 to 21 March 2026"             — day range in one month
 *   "27 April 2026 to 2 May 2026"     — cross-month range
 *   "15 August 2026"                  — single day (defensive)
 *
 * Venue strings carry a city name ("SANDF Range, Eeufees Road,
 * Pretoria"). Province is inferred from a short keyword map and
 * falls back to null — the shared persistence layer then marks the
 * row needsReview so staff can confirm. A venue string of "POSTAL"
 * (a mail-in postal match) is treated as having no venue.
 */
class SapfCalendarImporter
{
    public function __construct(private CalendarImporter $importer) {}

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    public function import(): array
    {
        $html = Http::timeout(20)
            ->withUserAgent('ShootingSportsBot/1.0 (+https://shootingsports.co.za)')
            ->get('https://www.samarksman.co.za/')
            ->throw()
            ->body();

        $host = $this->importer->federation(
            'sapf',
            'South African Pistol Federation',
            'https://www.samarksman.co.za',
        );

        return $this->importer->persist($host, $this->parse($html));
    }

    /**
     * @return list<ImportedMatch>
     */
    public function parse(string $html): array
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new DOMXPath($dom);
        $rows = $xpath->query('//table//tr');

        if ($rows === false) {
            return [];
        }

        $matches = [];

        foreach ($rows as $row) {
            if (! $row instanceof DOMElement) {
                continue;
            }

            $cells = [];

            foreach ($row->getElementsByTagName('td') as $cell) {
                $cells[] = trim(preg_replace('/\s+/u', ' ', $cell->textContent) ?? '');
            }

            // Need at least title, date and venue. Header rows
            // ("EVENT | DATE | VENUE | ...") land in `<th>` and
            // produce zero `<td>` cells, so they drop out here.
            if (count($cells) < 3) {
                continue;
            }

            $title = $cells[0];
            $dateText = $cells[1];
            $venueText = $cells[2];

            // The page also hosts a "Results 2025" table whose first
            // column is the same event name but whose second column
            // is the word "NPA/PPC" / "ISSF" (link text), not a date.
            // Reject anything the date parser cannot read.
            [$startsAt, $endsAt] = $this->dates($dateText);

            if ($startsAt === null) {
                continue;
            }

            [$venueName, $venueTown] = $this->splitVenue($venueText);
            $province = $this->inferProvince($venueText, $title);

            $slug = 'sapf-'.Str::slug($title.' '.$startsAt->toDateString());

            $matches[] = new ImportedMatch(
                externalId: $slug,
                title: $title,
                entryUrl: 'https://www.samarksman.co.za/#'.$slug,
                startsAt: $startsAt,
                endsAt: $endsAt,
                level: $this->level($title),
                status: $startsAt->isPast() ? EventStatus::Completed : EventStatus::Confirmed,
                province: $province,
                venueName: $venueName,
                disciplineSlug: 'target-pistol',
                description: null,
                venueTown: $venueTown,
                needsReview: $province === null,
                importNotes: $province === null
                    ? 'SAPF feed did not give a province for "'.$venueText.'". Set it in Desk.'
                    : null,
            );
        }

        return $matches;
    }

    /**
     * Parse a free-text SAPF date range into [start, end].
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function dates(string $value): array
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        if ($value === '') {
            return [null, null];
        }

        // "27 April 2026 to 2 May 2026" — cross-month range (also
        // covers an in-month range written out longhand).
        if (preg_match('/^(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})\s+to\s+(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})$/u', $value, $m)) {
            $start = $this->dateFrom((int) $m[1], $m[2], (int) $m[3]);
            $end = $this->dateFrom((int) $m[4], $m[5], (int) $m[6]);

            return [$start, $end];
        }

        // "19 to 21 March 2026" — day range in one month.
        if (preg_match('/^(\d{1,2})\s+to\s+(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})$/u', $value, $m)) {
            $start = $this->dateFrom((int) $m[1], $m[3], (int) $m[4]);
            $end = $this->dateFrom((int) $m[2], $m[3], (int) $m[4]);

            return [$start, $end];
        }

        // "15 August 2026" — single-day match.
        if (preg_match('/^(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})$/u', $value, $m)) {
            return [$this->dateFrom((int) $m[1], $m[2], (int) $m[3]), null];
        }

        return [null, null];
    }

    private function dateFrom(int $day, string $monthName, int $year): ?Carbon
    {
        $month = $this->monthIndex($monthName);

        if ($month === null) {
            return null;
        }

        return Carbon::create($year, $month, $day, 0, 0, 0, 'Africa/Johannesburg')?->startOfDay() ?: null;
    }

    private function monthIndex(string $name): ?int
    {
        return match (strtolower(substr($name, 0, 3))) {
            'jan' => 1,
            'feb' => 2,
            'mar' => 3,
            'apr' => 4,
            'may' => 5,
            'jun' => 6,
            'jul' => 7,
            'aug' => 8,
            'sep' => 9,
            'oct' => 10,
            'nov' => 11,
            'dec' => 12,
            default => null,
        };
    }

    /**
     * "SANDF Range, Eeufees Road, Pretoria" -> ["SANDF Range", "Pretoria"]
     * "Cape Town, Bellville Pistol Club" -> ["Bellville Pistol Club", "Bellville"]
     * "POSTAL" -> [null, null]  (mail-in match)
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function splitVenue(string $value): array
    {
        $value = trim($value);

        if ($value === '' || strcasecmp($value, 'POSTAL') === 0) {
            return [null, null];
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', $value)), fn (string $v): bool => $v !== ''));

        if ($parts === []) {
            return [null, null];
        }

        // The last comma-separated chunk is almost always a town.
        // If the first chunk looks like a town name (two or fewer
        // words, matches a known city), swap so the longer "club"
        // part becomes the venue name — this handles the inverted
        // "Cape Town, Bellville Pistol Club" shape.
        if (count($parts) >= 2 && $this->looksLikeTown($parts[0]) && ! $this->looksLikeTown($parts[count($parts) - 1])) {
            $town = $parts[0];
            $venue = $parts[count($parts) - 1];
        } else {
            $town = $parts[count($parts) - 1];
            $venue = $parts[0];
        }

        return [$venue, $town];
    }

    private function looksLikeTown(string $value): bool
    {
        return $this->provinceFromCity($value) !== null;
    }

    private function inferProvince(string $venueText, string $title): ?Province
    {
        // Province lives in either the venue string or the title
        // ("Western Province Provincial", "Kwa-Zulu Natal Provincial").
        $haystack = $venueText.' '.$title;

        foreach ($this->provinceKeywords() as $needle => $province) {
            if (stripos($haystack, $needle) !== false) {
                return $province;
            }
        }

        return null;
    }

    private function provinceFromCity(string $value): ?Province
    {
        $value = trim($value);

        foreach ($this->provinceKeywords() as $needle => $province) {
            if (strcasecmp($value, $needle) === 0) {
                return $province;
            }
        }

        return null;
    }

    /**
     * Keyword map used for province inference. Ordered so the more
     * specific province name beats a city that is a substring of
     * another (e.g. "Western Cape" before "Cape Town", "Northern
     * Cape" before any shorter "Cape" match).
     *
     * @return array<string, Province>
     */
    private function provinceKeywords(): array
    {
        return [
            'Western Province' => Province::WesternCape,
            'Western Cape' => Province::WesternCape,
            'Northern Cape' => Province::NorthernCape,
            'Eastern Cape' => Province::EasternCape,
            'North West' => Province::NorthWest,
            'Free State' => Province::FreeState,
            'KwaZulu-Natal' => Province::KwaZuluNatal,
            'Kwa-Zulu Natal' => Province::KwaZuluNatal,
            'KwaZulu Natal' => Province::KwaZuluNatal,
            'Central Gauteng' => Province::Gauteng,
            'Gauteng' => Province::Gauteng,
            'Mpumalanga' => Province::Mpumalanga,
            'Limpopo' => Province::Limpopo,
            'Pretoria' => Province::Gauteng,
            'Johannesburg' => Province::Gauteng,
            'Centurion' => Province::Gauteng,
            'Midrand' => Province::Gauteng,
            'Bellville' => Province::WesternCape,
            'Cape Town' => Province::WesternCape,
            'Stellenbosch' => Province::WesternCape,
            'Durban' => Province::KwaZuluNatal,
            'Pietermaritzburg' => Province::KwaZuluNatal,
            'Bloemfontein' => Province::FreeState,
            'Port Elizabeth' => Province::EasternCape,
            'Gqeberha' => Province::EasternCape,
            'East London' => Province::EasternCape,
            'Kimberley' => Province::NorthernCape,
            'Polokwane' => Province::Limpopo,
            'Nelspruit' => Province::Mpumalanga,
            'Mbombela' => Province::Mpumalanga,
            'Potchefstroom' => Province::NorthWest,
            'Rustenburg' => Province::NorthWest,
        ];
    }

    private function level(string $title): EventLevel
    {
        $lower = strtolower($title);

        if (str_contains($lower, 'national')) {
            return EventLevel::National;
        }

        if (str_contains($lower, 'provincial')) {
            return EventLevel::Provincial;
        }

        return EventLevel::Series;
    }
}
