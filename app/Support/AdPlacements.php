<?php

namespace App\Support;

use App\Enums\AdPage;
use App\Enums\PlacementSlot;
use App\Models\Placement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class AdPlacements
{
    /**
     * @return Collection<int, Placement>
     */
    public static function for(AdPage|string $page, PlacementSlot|string $slot, int $limit = 3): Collection
    {
        $pageValue = $page instanceof AdPage ? $page->value : $page;
        $slotValue = $slot instanceof PlacementSlot ? $slot->value : $slot;
        $today = now('Africa/Johannesburg')->toDateString();

        return Placement::query()
            ->with(['provider', 'adSlot'])
            ->where('is_active', true)
            ->whereDate('starts_on', '<=', $today)
            ->whereDate('ends_on', '>=', $today)
            ->whereHas('adSlot', function ($query) use ($pageValue, $slotValue) {
                $query->where('page', $pageValue)
                    ->where('slot', $slotValue)
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
