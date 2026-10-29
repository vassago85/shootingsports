<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds `home_venue_id` so shooters can pick a home range that
     * the `/me/feed` "Near you" bucket uses as the radius centre.
     * Nullable + nullOnDelete so a venue being deleted (or never
     * picked) is a no-op for the user, not a cascade.
     *
     * Travel radius already lives on `users.travel_radius_km`; the
     * feed combines the two to filter upcoming matches by distance.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('home_venue_id')
                ->nullable()
                ->after('travel_radius_km')
                ->constrained('venues')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('home_venue_id');
        });
    }
};
