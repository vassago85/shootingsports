<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            // Free-form staff note from the importer. Multi-venue
            // source strings and inferred end-dates land here so
            // staff can see why the row was flagged.
            $table->text('import_notes')->nullable()->after('last_verified_at');

            // Importer-set flag that this row wants eyes. Staff see
            // it as a filter and a column on the admin Events list;
            // the public calendar does not treat it specially.
            $table->boolean('needs_review')->default(false)->after('import_notes');

            $table->index('needs_review');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropIndex(['needs_review']);
            $table->dropColumn(['import_notes', 'needs_review']);
        });
    }
};
