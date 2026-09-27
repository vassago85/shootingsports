<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('kind')->default('competition')->after('level');
            $table->boolean('accepts_platform_entries')->default(false)->after('entry_url');
            $table->string('entry_collection')->default('external')->after('accepts_platform_entries');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['kind', 'accepts_platform_entries', 'entry_collection']);
        });
    }
};
