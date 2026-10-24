<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reset `organisations.visitors_welcome` to NULL so the Visitors
 * badge is only shown for clubs where an administrator has
 * deliberately said "yes".
 *
 * Up to this point the column defaulted to `false` and the factory
 * flipped roughly 40% of rows to `true` at random, so every "yes"
 * in production carried the same signal as a coin toss. There is
 * no audit trail on the column to distinguish admin-entered values
 * from factory-generated noise, so the safe reset is to blank
 * every row — admins can re-affirm genuine clubs via the Filament
 * organisation form at their own pace.
 *
 * Not reversible: there is no record of the previous values and
 * no way to re-synthesise the random factory output.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('organisations')->update(['visitors_welcome' => null]);
    }

    public function down(): void
    {
        // Intentionally empty — the previous values were a mix of
        // factory randomness and (unknown) admin input, and cannot
        // be restored. Rolling back the preceding migration will
        // re-add the NOT NULL + false default if that is needed.
    }
};
