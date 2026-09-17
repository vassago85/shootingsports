<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot for multi-venue matches. `events.venue_id` remains the
     * primary/back-compat single-venue column and is kept in sync
     * with the pivot row that has `sort_order = 0`, so existing
     * filters, JSON-LD, map pins, and importers continue to work
     * unchanged during the dual-read window.
     */
    public function up(): void
    {
        Schema::create('event_venue', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();

            // Optional day label ("Day 1", "Saturday", "Rifle stages")
            // rendered next to the venue on the public detail page.
            $table->string('day_label')->nullable();

            // Optional per-venue date for multi-day matches so
            // "Papaberg on 5 Oct, Legends on 6 Oct" can be shown
            // without polluting the event's own start/end.
            $table->date('starts_on')->nullable();

            // Lowest sort_order is the primary/back-compat venue.
            // Kept in sync with `events.venue_id`.
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->unique(['event_id', 'venue_id']);
            $table->index(['event_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_venue');
    }
};
