<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a nullable banner_path column so staff can upload a banner directly
 * from the Filament event form to the "media" filesystem disk (which is
 * bind-mounted to the extra HDD on the server via docker-compose.prod.yml).
 *
 * The legacy banner_media_id FK to the media table stays in place so any
 * existing Media-backed banners keep resolving; the event-card view prefers
 * banner_path when both are set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->string('banner_path')->nullable()->after('banner_media_id');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('banner_path');
        });
    }
};
