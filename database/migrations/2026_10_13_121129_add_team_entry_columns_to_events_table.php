<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->boolean('accepts_team_entries')->default(false)->after('entry_collection');
            $table->unsignedTinyInteger('team_size')->nullable()->after('accepts_team_entries');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn(['accepts_team_entries', 'team_size']);
        });
    }
};
