<?php

use App\Enums\Province;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Allow `venues.town` to be null and clear any historical rows whose
 * `town` column is actually a province name.
 *
 * The Filament form rejects town == province via `NotAProvinceName`,
 * and `Event::locationLabel()` guards against it at render-time, but
 * legacy rows (Legends Adventure Farm = "Gauteng", Taai Boskraal =
 * "Western Cape") still exist in the database. Nulling them out
 * lets the card fall back to the province code and clearly marks
 * them as missing in admin exports and the Desk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table): void {
            $table->string('town')->nullable()->change();
        });

        $candidates = collect(Province::cases())
            ->flatMap(fn (Province $p): array => [
                $p->getLabel(),
                $p->value,
                str_replace('_', ' ', $p->value),
            ])
            ->map(fn (string $v): string => mb_strtolower($v))
            ->unique()
            ->values()
            ->all();

        DB::table('venues')
            ->whereNotNull('town')
            ->whereRaw(
                'LOWER(TRIM(town)) IN ('.implode(',', array_fill(0, count($candidates), '?')).')',
                $candidates,
            )
            ->update(['town' => null]);
    }

    public function down(): void
    {
        // Irreversible: we cannot know what the correct town was.
        // The nullable column stays — reinstating NOT NULL would
        // need every row to have a real town first.
    }
};
