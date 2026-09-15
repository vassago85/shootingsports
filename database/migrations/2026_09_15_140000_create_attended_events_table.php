<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personal shooting log.
 *
 * The core value prop: a shooter builds up an unforgeable record of
 * every match they attended, exportable as an annual PDF for their
 * accrediting body (SAPSA / PISA / KKSA / etc.) at dedicated-status
 * renewal time. Locks a Pro subscriber in a little more every year.
 *
 * Snapshot fields are denormalised on purpose:
 *   - `event_id` is nullable so users can log old matches that never
 *     lived in our calendar. When present, the FK is a soft link.
 *   - `event_name_snapshot`, `event_date`, `discipline_name_snapshot`,
 *     `venue_snapshot`, `host_snapshot` are ALWAYS the source of truth
 *     for display. If the referenced event/discipline gets renamed or
 *     deleted years later, the log entry still shows what actually
 *     happened at the time the shooter logged it — that's what the
 *     accrediting body cares about.
 *   - `discipline_id` FK is kept for filtering / per-discipline totals
 *     on the PDF, but nulled on delete rather than cascading.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attended_events', function (Blueprint $table): void {
            $table->id();

            // Owner
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Soft links to the source records (nullable to allow
            // logging pre-app matches + surviving event deletion)
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('discipline_id')->nullable()->constrained()->nullOnDelete();

            // Immutable snapshot — the source of truth for display + PDF
            $table->string('event_name_snapshot');
            $table->date('event_date');
            $table->string('discipline_name_snapshot')->nullable();
            $table->string('venue_snapshot')->nullable();
            $table->string('host_snapshot')->nullable();

            // Optional result fields — everything except date is
            // optional so a shooter can log "I was there" with zero
            // extra typing on the match page.
            $table->string('division')->nullable();
            $table->string('classification')->nullable();
            $table->unsignedSmallInteger('placing')->nullable();
            $table->unsignedSmallInteger('field_size')->nullable();
            $table->string('score')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            // A shooter should not double-log the same event. Manual
            // entries (event_id null) can repeat freely — they might
            // have shot the same club's Wednesday shoot multiple times.
            $table->unique(['user_id', 'event_id']);

            $table->index(['user_id', 'event_date']);
            $table->index(['user_id', 'discipline_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attended_events');
    }
};
