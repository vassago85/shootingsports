<?php

namespace App\Support;

use App\Enums\Province;
use App\Models\CalendarImportRun;
use App\Models\Event;
use App\Models\Venue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Finds venue/province mismatches, events with no venue, titles that
 * disagree with their start date, and import sources that went quiet.
 */
final class DataQualityReport
{
    /**
     * @return list<string>
     */
    public function findings(): array
    {
        return array_values(array_filter([
            ...$this->provinceMismatches(),
            ...$this->eventsWithoutVenue(),
            ...$this->titleMismatches(),
            ...$this->importGaps(),
        ]));
    }

    /**
     * @return list<string>
     */
    private function provinceMismatches(): array
    {
        $findings = [];
        $geocoded = 0;

        Venue::query()
            ->whereNotNull('town')
            ->where('town', '!=', '')
            ->orderBy('id')
            ->each(function (Venue $venue) use (&$findings, &$geocoded): void {
                $resolved = $this->resolveProvince($venue, $geocoded);

                if ($resolved === null || $resolved === $venue->province) {
                    return;
                }

                $findings[] = sprintf(
                    '%s (%s) is stored as %s; geocoding places it in %s.',
                    $venue->name,
                    $venue->town,
                    $venue->province?->getLabel() ?? 'an empty province',
                    $resolved->getLabel(),
                );
            });

        return $findings;
    }

    private function resolveProvince(Venue $venue, int &$geocoded): ?Province
    {
        $fromTown = TownProvince::forTown($venue->town);

        if ($fromTown instanceof Province) {
            return $fromTown;
        }

        if ($venue->lat !== null && $venue->lng !== null) {
            return Geo::nearestProvince((float) $venue->lat, (float) $venue->lng);
        }

        if ($geocoded >= 15 || app()->runningUnitTests()) {
            return null;
        }

        $geocoded++;

        return $this->nominatimProvince((string) $venue->town);
    }

    private function nominatimProvince(string $town): ?Province
    {
        $cacheKey = 'geo.town.'.md5(mb_strtolower($town));
        $cached = Cache::get($cacheKey);

        if (is_string($cached)) {
            return Province::tryFrom($cached);
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'User-Agent' => 'ShootingSportsBot/1.0 (+https://shootingsports.co.za; data-quality)',
                    'Accept' => 'application/json',
                ])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $town.', South Africa',
                    'format' => 'jsonv2',
                    'limit' => 1,
                    'countrycodes' => 'za',
                    'addressdetails' => 1,
                ]);

            usleep(1_100_000);

            $state = $response->json('0.address.state');
            $province = TownProvince::fromLabel(is_string($state) ? $state : null);

            if ($province instanceof Province) {
                Cache::put($cacheKey, $province->value, now()->addDays(30));
            }

            return $province;
        } catch (Throwable $exception) {
            Log::warning('Venue town geocode failed.', [
                'town' => $town,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return list<string>
     */
    private function eventsWithoutVenue(): array
    {
        return Event::query()
            ->upcoming()
            ->excludingVerificationFixtures()
            ->whereNull('venue_id')
            ->whereDoesntHave('venues')
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Event $event): string => $event->title.' ('.$event->starts_at?->timezone((string) config('app.timezone'))->format('j M Y').') has no venue.')
            ->all();
    }

    /**
     * @return list<string>
     */
    private function titleMismatches(): array
    {
        $months = [
            'january' => 1, 'february' => 2, 'march' => 3, 'april' => 4,
            'may' => 5, 'june' => 6, 'july' => 7, 'august' => 8,
            'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12,
        ];
        $seasons = [
            'summer' => [12, 1, 2],
            'autumn' => [3, 4, 5],
            'winter' => [6, 7, 8],
            'spring' => [9, 10, 11],
        ];

        $findings = [];

        Event::query()
            ->upcoming()
            ->excludingVerificationFixtures()
            ->whereNotNull('starts_at')
            ->orderBy('starts_at')
            ->each(function (Event $event) use ($months, $seasons, &$findings): void {
                $start = $event->starts_at->timezone((string) config('app.timezone'));
                $end = ($event->ends_at ?? $event->starts_at)->timezone((string) config('app.timezone'));
                $title = mb_strtolower($event->title);
                $allowedMonths = array_unique([$start->month, $end->month]);
                $allowedYears = array_unique([$start->year, $end->year]);

                foreach ($months as $name => $number) {
                    if (preg_match('/\b'.$name.'\b/', $title) === 1 && ! in_array($number, $allowedMonths, true)) {
                        $findings[] = $event->title.' starts '.$start->format('j M Y').' but the title names '.$name.'.';
                    }
                }

                if (preg_match_all('/\b((?:19|20)\d{2})\b/', $event->title, $years) > 0) {
                    foreach ($years[0] as $year) {
                        if (! in_array((int) $year, $allowedYears, true)) {
                            $findings[] = $event->title.' starts '.$start->format('j M Y').' but the title names '.$year.'.';
                        }
                    }
                }

                foreach ($seasons as $season => $seasonMonths) {
                    if (preg_match('/\b'.$season.'\b/', $title) === 1 && ! in_array($start->month, $seasonMonths, true)) {
                        $findings[] = $event->title.' starts '.$start->format('j M Y').' but the title says '.$season.'.';
                    }
                }
            });

        return $findings;
    }

    /**
     * @return list<string>
     */
    private function importGaps(): array
    {
        $findings = [];
        $prefixes = [
            'saprf' => 'saprf-event-',
            'cgpsa' => 'cgpsa-event-',
            'vektor' => 'vektor-event-',
            'mpsa' => 'mpsa-',
        ];

        foreach (CalendarImportSettings::sources() as $source) {
            $key = $source['key'];
            $prefix = $prefixes[$key] ?? $key.'-';
            $ours = Event::query()->upcoming()->where('slug', 'like', $prefix.'%')->count();
            $run = CalendarImportRun::query()
                ->where('source', $key)
                ->whereNull('error')
                ->latest('id')
                ->first();

            if ($run instanceof CalendarImportRun && $run->matchesFound() > 0 && $ours === 0 && $run->created_at?->gt(now()->subDays(14))) {
                $findings[] = $source['name'].': the last import saw '.$run->matchesFound().' matches and none are upcoming on this calendar.';
            }

            if (app()->runningUnitTests()) {
                continue;
            }

            try {
                $response = Http::timeout(8)->get($source['url']);

                if (! $response->successful()) {
                    $findings[] = $source['name'].': source site returned HTTP '.$response->status().'.';
                }
            } catch (Throwable $exception) {
                Log::warning('Calendar source check failed.', [
                    'source' => $key,
                    'message' => $exception->getMessage(),
                ]);
                $findings[] = $source['name'].': source site could not be reached.';
            }
        }

        return $findings;
    }
}
