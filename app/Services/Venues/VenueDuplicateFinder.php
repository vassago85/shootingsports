<?php

namespace App\Services\Venues;

use App\Enums\ListingStatus;
use App\Models\Venue;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class VenueDuplicateFinder
{
    /**
     * Groups of likely duplicate venues (2+ each).
     *
     * @return list<array{key: string, venues: Collection<int, Venue>}>
     */
    public function groups(): array
    {
        $venues = Venue::query()
            ->where('status', '!=', ListingStatus::Archived)
            ->orderBy('name')
            ->get();

        /** @var array<string, Collection<int, Venue>> $buckets */
        $buckets = [];

        foreach ($venues as $venue) {
            $key = $this->fingerprint($venue);
            $buckets[$key] ??= collect();
            $buckets[$key]->push($venue);
        }

        $groups = [];

        foreach ($buckets as $key => $group) {
            if ($group->count() < 2) {
                continue;
            }

            $groups[] = [
                'key' => $key,
                'venues' => $group->values(),
            ];
        }

        // Secondary: same town + high name similarity across different fingerprints.
        $byTown = $venues->groupBy(fn (Venue $v): string => Str::lower(trim((string) $v->town)));

        foreach ($byTown as $town => $townVenues) {
            if ($town === '' || $townVenues->count() < 2) {
                continue;
            }

            $list = $townVenues->values();

            for ($i = 0; $i < $list->count(); $i++) {
                for ($j = $i + 1; $j < $list->count(); $j++) {
                    $a = $list[$i];
                    $b = $list[$j];

                    if ($this->fingerprint($a) === $this->fingerprint($b)) {
                        continue; // already in exact bucket
                    }

                    if ($this->similarEnough($a->name, $b->name)) {
                        $pairKey = 'sim:'.min($a->id, $b->id).'-'.max($a->id, $b->id);
                        $groups[] = [
                            'key' => $pairKey,
                            'venues' => collect([$a, $b]),
                        ];
                    }
                }
            }
        }

        usort($groups, fn (array $x, array $y): int => $y['venues']->count() <=> $x['venues']->count());

        return $groups;
    }

    public function fingerprint(Venue $venue): string
    {
        $name = $this->normaliseName($venue->name);
        $town = Str::lower(trim((string) $venue->town));

        return $name.'|'.$town;
    }

    public function normaliseName(string $name): string
    {
        $name = Str::lower($name);
        $name = preg_replace('/\b(shooting|range|club|gun|skiet|baan|sport)\b/u', '', $name) ?? $name;
        $name = preg_replace('/[^a-z0-9]+/u', '', $name) ?? $name;

        return $name;
    }

    public function similarEnough(string $a, string $b): bool
    {
        $na = $this->normaliseName($a);
        $nb = $this->normaliseName($b);

        if ($na === '' || $nb === '') {
            return false;
        }

        if ($na === $nb) {
            return true;
        }

        similar_text($na, $nb, $percent);

        return $percent >= 85.0;
    }
}
