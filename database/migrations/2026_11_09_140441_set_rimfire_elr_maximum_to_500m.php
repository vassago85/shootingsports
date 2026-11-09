<?php

use App\Models\Discipline;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Discipline::query()
            ->where('slug', '22lr-elr')
            ->update(['typical_distances' => '50–500 m']);
    }

    public function down(): void
    {
        Discipline::query()
            ->where('slug', '22lr-elr')
            ->where('typical_distances', '50–500 m')
            ->update(['typical_distances' => '50–750 m']);
    }
};
