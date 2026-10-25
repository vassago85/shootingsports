<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fix two long-standing data defects on the Wattlespring venues.
 *
 * The site had two near-identical venue rows for the same real-world
 * place in Bapsfontein:
 *
 *  - [13] "Wattlespring Sport Shooting Club"  — town = "Pretoria"
 *         (wrong — Pretoria is where SA Jagters Impala, the host
 *         organisation, is based, not where the range sits)
 *  - [39] "Wattlespring Sports Shooting Club" — town = "Bronkhorstspruit"
 *         (also wrong, and an unused duplicate)
 *
 * Row 13 is the canonical venue (used by Impala Haelgeweer Skietdag,
 * CTSASA Olympic Trial, CTSASA Chairman's Cup). Row 39 has zero
 * events pointing at it (verified via Event::where('venue_id', 39)
 * on 2026-10-25).
 *
 * This migration:
 *   1. Updates venue 13's town to the correct value ("Bapsfontein")
 *      — supplied by the project owner in the follow-up brief, not
 *      inferred.
 *   2. Soft-deletes venue 39 so the duplicate stops showing up in
 *      Filament pickers. SoftDeletes keeps the row recoverable in
 *      case the merge call turns out to be wrong.
 *
 * The matches themselves (slugs listed below for forensic clarity)
 * flip from "Pretoria · GP" to "Bapsfontein · GP" automatically on
 * the next API read — no code change is needed on top of this.
 *
 * Reversible via down().
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('venues')
            ->where('id', 13)
            ->where('name', 'Wattlespring Sport Shooting Club')
            ->update([
                'town' => 'Bapsfontein',
                'updated_at' => now(),
            ]);

        DB::table('venues')
            ->where('id', 39)
            ->where('name', 'Wattlespring Sports Shooting Club')
            ->update([
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('venues')
            ->where('id', 13)
            ->where('name', 'Wattlespring Sport Shooting Club')
            ->update([
                'town' => 'Pretoria',
                'updated_at' => now(),
            ]);

        DB::table('venues')
            ->where('id', 39)
            ->where('name', 'Wattlespring Sports Shooting Club')
            ->update([
                'deleted_at' => null,
                'updated_at' => now(),
            ]);
    }
};
