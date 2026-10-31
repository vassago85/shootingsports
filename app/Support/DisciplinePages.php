<?php

namespace App\Support;

use App\Enums\EventStatus;
use App\Enums\Province;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Venue;
use App\Queries\PublicEventQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Live copy, indexability and freshness for a discipline hub and its
 * province pages. Every sentence is built from stored rows.
 */
final class DisciplinePages
{
    public static function title(Discipline $discipline, ?Province $province, int $upcoming): string
    {
        if ($province === null) {
            if ($upcoming === 0) {
                return $discipline->name.' in South Africa: clubs, ranges and next matches';
            }

            $events = $upcoming === 1 ? '1 upcoming event' : $upcoming.' upcoming events';

            return $discipline->name.' matches in South Africa: '.$events;
        }

        return $discipline->name.' matches in '.$province->getLabel().': '.$upcoming.' upcoming';
    }

    /**
     * @param  Collection<int, Event>  $upcoming
     * @param  Collection<int, Province>  $clubProvinces
     */
    public static function paragraph(
        Discipline $discipline,
        ?Province $province,
        Collection $upcoming,
        Collection $clubProvinces,
        ?Organisation $federation,
    ): string {
        $where = $province !== null ? ' in '.$province->getLabel() : ' in South Africa';
        $count = $upcoming->count();
        $sentences = [];

        if ($count === 0) {
            $sentences[] = 'No upcoming '.$discipline->name.' match is listed'.$where.'.';
        } else {
            $sentences[] = $count === 1
                ? 'There is 1 upcoming '.$discipline->name.' match'.$where.'.'
                : 'There are '.$count.' upcoming '.$discipline->name.' matches'.$where.'.';

            foreach ($upcoming->take(3) as $event) {
                $sentences[] = self::eventSentence($event);
            }
        }

        if ($clubProvinces->isNotEmpty()) {
            $sentences[] = 'Clubs list '.$discipline->name.' in '.self::joinLabels($clubProvinces).'.';
        }

        if ($federation !== null) {
            $sentences[] = $federation->name.' is the linked governing body.';
        }

        return implode(' ', $sentences);
    }

    /**
     * @param  Collection<int, Organisation>  $clubs
     * @param  Collection<int, Venue>  $ranges
     * @return list<array{label: string, clubs: int, ranges: int, url: string}>
     */
    public static function directory(Collection $clubs, Collection $ranges, Discipline $discipline): array
    {
        $rows = [];

        foreach (Province::cases() as $province) {
            $clubCount = $clubs->filter(fn (Organisation $club): bool => $club->province === $province)->count();
            $rangeCount = $ranges->filter(fn (Venue $range): bool => $range->province === $province)->count();

            if ($clubCount === 0 && $rangeCount === 0) {
                continue;
            }

            $rows[] = [
                'label' => $province->getLabel(),
                'clubs' => $clubCount,
                'ranges' => $rangeCount,
                'url' => route('disciplines.province', [$discipline->slug, $province->urlSlug()]),
            ];
        }

        return $rows;
    }

    /**
     * @return Collection<int, Event>
     */
    public static function recentResults(Discipline $discipline, ?Province $province): Collection
    {
        return self::eventsInProvince($discipline, $province)
            ->where('status', EventStatus::Completed)
            ->with(['venue', 'hostOrganisation'])
            ->orderByDesc('starts_at')
            ->limit(5)
            ->get();
    }

    /**
     * @return Collection<int, Event>
     */
    public static function nationalUpcoming(Discipline $discipline): Collection
    {
        return (new PublicEventQuery(disciplineSlug: $discipline->slug))->get()->take(5)->values();
    }

    public static function hasPublicRecord(Discipline $discipline, Province $province): bool
    {
        return self::eventsInProvince($discipline, $province)->exists()
            || self::clubsInProvince($discipline, $province)->exists()
            || self::rangesInProvince($discipline, $province)->exists();
    }

    public static function lastmod(Discipline $discipline, Province $province): ?string
    {
        $candidates = array_filter([
            self::eventsInProvince($discipline, $province)->max('updated_at'),
            self::clubsInProvince($discipline, $province)->max('updated_at'),
            self::rangesInProvince($discipline, $province)->max('updated_at'),
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        if ($candidates === []) {
            return null;
        }

        return Carbon::parse(max($candidates))
            ->timezone((string) config('app.timezone'))
            ->toAtomString();
    }

    /**
     * @param  Collection<int, Province>  $provinces
     */
    private static function joinLabels(Collection $provinces): string
    {
        $labels = $provinces
            ->map(fn (Province $province): string => $province->getLabel())
            ->values();

        if ($labels->count() <= 1) {
            return (string) $labels->first();
        }

        $last = $labels->pop();

        return $labels->implode(', ').' and '.$last;
    }

    private static function eventSentence(Event $event): string
    {
        $venue = $event->venue;
        $host = $event->hostOrganisation;
        $town = filled($venue?->town) ? $venue->town : $host?->town;
        $province = $venue?->province ?? ($event->venue_id === null ? $host?->province : null);
        $place = collect([$venue?->name, $town, $province?->getLabel()])
            ->filter(fn (mixed $value): bool => filled($value))
            ->implode(', ');
        $date = $event->starts_at?->timezone((string) config('app.timezone'))->format('j M Y');

        $sentence = $event->title;

        if ($place !== '') {
            $sentence .= ' is at '.$place;
        }

        if ($date !== null) {
            $sentence .= ' on '.$date;
        }

        return $sentence.'.';
    }

    /**
     * @return Builder<Event>
     */
    private static function eventsInProvince(Discipline $discipline, ?Province $province): Builder
    {
        $tree = $discipline->treeIds();

        return Event::query()
            ->published()
            ->excludingVerificationFixtures()
            ->whereHas('disciplines', fn (Builder $query) => $query->whereIn('disciplines.id', $tree))
            ->when($province !== null, function (Builder $query) use ($province): void {
                $query->where(function (Builder $placed) use ($province): void {
                    $placed->whereHas('venue', fn (Builder $venue) => $venue->where('province', $province))
                        ->orWhere(function (Builder $hosted) use ($province): void {
                            $hosted->whereNull('venue_id')
                                ->whereHas('hostOrganisation', fn (Builder $org) => $org->where('province', $province));
                        });
                });
            });
    }

    /**
     * @return Builder<Organisation>
     */
    private static function clubsInProvince(Discipline $discipline, Province $province): Builder
    {
        return Organisation::query()
            ->published()
            ->clubs()
            ->where('province', $province)
            ->whereHas('disciplines', fn (Builder $query) => $query->whereIn('disciplines.id', $discipline->treeIds()));
    }

    /**
     * @return Builder<Venue>
     */
    private static function rangesInProvince(Discipline $discipline, Province $province): Builder
    {
        return Venue::query()
            ->published()
            ->where('province', $province)
            ->whereHas('disciplines', fn (Builder $query) => $query->whereIn('disciplines.id', $discipline->treeIds()));
    }
}
