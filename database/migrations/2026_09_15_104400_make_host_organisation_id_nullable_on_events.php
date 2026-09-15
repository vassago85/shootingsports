<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Some matches are hosted by the range operator, not by an
     * external club — a fundraiser at a private ridge range is the
     * canonical case. The read side is already null-safe
     * (`hostOrganisation?->name`, JsonLd guards, iCal ?->name, the
     * PublicEventQuery already anticipates hostless events); we
     * loosen the schema to match.
     *
     * The FK constraint is preserved via nullOnDelete so removing an
     * organisation nulls the host on any surviving event.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropForeign(['host_organisation_id']);
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->foreignId('host_organisation_id')
                ->nullable()
                ->change()
                ->constrained('organisations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropForeign(['host_organisation_id']);
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->foreignId('host_organisation_id')
                ->nullable(false)
                ->change()
                ->constrained('organisations')
                ->restrictOnDelete();
        });
    }
};
