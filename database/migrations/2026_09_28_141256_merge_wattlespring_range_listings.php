<?php

use App\Enums\ListingStatus;
use App\Models\Venue;
use App\Models\VenueAlias;
use App\Services\Venues\VenueMerger;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $primary = Venue::query()->where('slug', 'wattlespring-sport-shooting-club')->first();
        $duplicate = Venue::query()->where('slug', 'wattlespring-sports-shooting-club')->first();

        if ($primary === null || $duplicate === null || $duplicate->status === ListingStatus::Archived) {
            return;
        }

        app(VenueMerger::class)->merge($primary, [$duplicate]);
    }

    public function down(): void
    {
        $primary = Venue::query()->where('slug', 'wattlespring-sport-shooting-club')->first();
        $duplicate = Venue::query()->where('slug', 'wattlespring-sports-shooting-club')->first();

        if ($duplicate !== null && $duplicate->status === ListingStatus::Archived) {
            $duplicate->update(['status' => ListingStatus::Published]);
        }

        if ($primary !== null) {
            VenueAlias::query()
                ->where('venue_id', $primary->id)
                ->where('slug', 'wattlespring-sports-shooting-club')
                ->delete();
        }
    }
};
