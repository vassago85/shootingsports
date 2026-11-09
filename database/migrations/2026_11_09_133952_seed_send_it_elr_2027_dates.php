<?php

use App\Models\Discipline;
use Database\Seeders\SendItElr2027Seeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Fresh installs seed this programme after the disciplines exist.
        // A database that already has ELR gets the dates on migrate.
        if (! Discipline::query()->where('slug', 'elr')->exists()) {
            return;
        }

        (new SendItElr2027Seeder)->run();
    }

    public function down(): void
    {
        //
    }
};
