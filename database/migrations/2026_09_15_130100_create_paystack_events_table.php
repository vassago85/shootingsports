<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Webhook idempotency + audit log.
 *
 * Paystack retries every 3 minutes for the first four attempts then
 * hourly for 72 hours if we do not 200 in time (or if we return non-2xx).
 * That means the same event will legitimately arrive multiple times.
 *
 * Rules:
 *   - `id` is the Paystack event id (from the payload, not our own
 *     autoincrement) so a unique-constraint violation is the cheap way
 *     to spot a duplicate.
 *   - `processed_at` stays null until the handler for this event
 *     finishes cleanly. That gives us a "stuck" query for staff.
 *   - `payload` is the full JSON body as received, verbatim, so we can
 *     replay by hand if the event handler is buggy the first time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paystack_events', function (Blueprint $table): void {
            // Paystack event id from the `id` field of the payload
            // (an integer, but stored as string to be robust to
            // Paystack API changes).
            $table->string('id')->primary();
            $table->string('event_type')->index();
            $table->string('reference')->nullable()->index();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable()->index();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paystack_events');
    }
};
