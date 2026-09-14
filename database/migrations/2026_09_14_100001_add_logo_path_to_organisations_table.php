<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a nullable logo_path column so clubs, associations, federations and
 * provincial bodies can upload a badge/logo directly from the Filament form.
 *
 * The file is written to the "media" filesystem disk, which on production is
 * bind-mounted to /mnt/storage/shootingsports/media on the extra HDD.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table): void {
            $table->string('logo_path')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table): void {
            $table->dropColumn('logo_path');
        });
    }
};
