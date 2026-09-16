<?php

namespace App\Services\Venues;

use App\Enums\GeocodeSource;
use App\Enums\ListingStatus;
use App\Models\Event;
use App\Models\Venue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class VenueMerger
{
    /**
     * Absorb $losers into $primary. Events move; losers are archived.
     *
     * @param  Collection<int, Venue>|list<Venue>  $losers
     * @return array{events: int, archived: int}
     */
    public function merge(Venue $primary, Collection|array $losers): array
    {
        $losers = Collection::wrap($losers)
            ->filter(fn (Venue $v): bool => $v->id !== $primary->id)
            ->unique('id')
            ->values();

        if ($losers->isEmpty()) {
            throw new InvalidArgumentException('Select at least one duplicate venue to merge.');
        }

        return DB::transaction(function () use ($primary, $losers): array {
            $loserIds = $losers->pluck('id')->all();

            $moved = Event::query()
                ->whereIn('venue_id', $loserIds)
                ->update(['venue_id' => $primary->id]);

            $this->backfillPrimary($primary, $losers);

            $archived = 0;

            foreach ($losers as $loser) {
                $loser->forceFill(['status' => ListingStatus::Archived])->save();
                $archived++;
            }

            return [
                'events' => $moved,
                'archived' => $archived,
            ];
        });
    }

    /**
     * @param  Collection<int, Venue>  $losers
     */
    private function backfillPrimary(Venue $primary, Collection $losers): void
    {
        $fillable = [
            'town', 'address', 'metro', 'max_distance_m', 'bay_count',
            'day_fee_cents', 'facilities', 'notes',
        ];

        $updates = [];

        foreach ($fillable as $field) {
            if (filled($primary->{$field})) {
                continue;
            }

            foreach ($losers as $loser) {
                if (filled($loser->{$field})) {
                    $updates[$field] = $loser->{$field};
                    break;
                }
            }
        }

        if (! $primary->hasCoordinates()) {
            $donor = $this->bestPinDonor($losers);

            if ($donor !== null) {
                $updates['lat'] = $donor->lat;
                $updates['lng'] = $donor->lng;
                $updates['geocode_source'] = $donor->geocode_source?->value ?? GeocodeSource::Nominatim->value;
                $updates['geocoded_at'] = $donor->geocoded_at ?? now();
            }
        }

        if ($updates !== []) {
            $primary->fill($updates)->save();
        }
    }

    /**
     * @param  Collection<int, Venue>  $losers
     */
    private function bestPinDonor(Collection $losers): ?Venue
    {
        $staff = $losers->first(
            fn (Venue $v): bool => $v->hasCoordinates() && $v->isStaffPinned()
        );

        if ($staff !== null) {
            return $staff;
        }

        return $losers->first(fn (Venue $v): bool => $v->hasCoordinates());
    }
}
