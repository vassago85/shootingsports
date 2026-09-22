<?php

namespace App\Support;

use App\Enums\AdPage;
use App\Enums\Division;
use App\Enums\PlacementSlot;
use App\Models\Discipline;
use App\Models\Placement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class AdPlacements
{
    /**
     * @return Collection<int, Placement>
     */
    public static function for(AdPage|string $page, PlacementSlot|string $slot, int $limit = 3, Division|string|null $division = null): Collection
    {
        $pageValue = $page instanceof AdPage ? $page->value : $page;
        $slotValue = $slot instanceof PlacementSlot ? $slot->value : $slot;
        $divisionValue = $division instanceof Division ? $division->value : $division;
        $today = now('Africa/Johannesburg')->toDateString();

        return Placement::query()
            ->with(['provider', 'adSlot'])
            ->where('is_active', true)
            ->whereDate('starts_on', '<=', $today)
            ->whereDate('ends_on', '>=', $today)
            ->when(
                filled($divisionValue),
                fn ($query) => $query
                    ->where('division', $divisionValue)
                    ->whereDoesntHave('disciplines'),
            )
            ->whereHas('adSlot', function ($query) use ($pageValue, $slotValue) {
                $query->where('page', $pageValue)
                    ->where('slot', $slotValue)
                    ->where('is_active', true);
            })
            ->orderByDesc('rate_cents')
            ->limit($limit)
            ->get();
    }

    /**
     * One sponsor per division, in the order given, capped so a sport
     * that sits in several divisions does not become a stack of banners.
     *
     * @param  list<Division>  $divisions
     * @return Collection<int, Placement>
     */
    public static function forDivisions(array $divisions, int $limit = 3): Collection
    {
        $placements = collect();

        foreach ($divisions as $division) {
            if ($placements->count() >= $limit) {
                break;
            }

            $placement = self::for(AdPage::Disciplines, PlacementSlot::CategorySponsor, 1, $division)->first();

            if ($placement !== null) {
                $placements->push($placement);
            }
        }

        return $placements;
    }

    /**
     * The sport advert for these disciplines. One booking can cover
     * several sports, so a PRS placement linked to Precision Rifle,
     * PRS, PR22, and NRL Hunter is returned once.
     *
     * @param  iterable<int, Discipline|int>  $disciplines
     * @return Collection<int, Placement>
     */
    public static function forDisciplines(iterable $disciplines, int $limit = 1): Collection
    {
        $ids = collect($disciplines)
            ->map(fn (Discipline|int $discipline): int => $discipline instanceof Discipline ? $discipline->getKey() : $discipline)
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $today = now('Africa/Johannesburg')->toDateString();

        return Placement::query()
            ->with(['provider', 'adSlot'])
            ->where('is_active', true)
            ->whereDate('starts_on', '<=', $today)
            ->whereDate('ends_on', '>=', $today)
            ->whereHas('disciplines', fn ($query) => $query->whereIn('disciplines.id', $ids))
            ->whereHas('adSlot', function ($query) {
                $query->where('page', AdPage::Disciplines->value)
                    ->where('slot', PlacementSlot::CategorySponsor->value)
                    ->where('is_active', true);
            })
            ->orderByDesc('rate_cents')
            ->limit($limit)
            ->get();
    }

    public static function imageUrl(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        return Storage::disk('media')->url($path);
    }
}
