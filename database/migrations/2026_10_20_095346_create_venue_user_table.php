<?php

use App\Enums\MembershipStatus;
use App\Models\Venue;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The venue ↔ user pivot that promotes range ownership from the
 * implicit `venues.claimed_by` scalar to a first-class capability.
 * Same shape as `provider_user` so the desk and the API can scope
 * ranges the same way they already scope shops. Existing
 * `claimed_by` rows are backfilled as approved owner memberships.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // owner | editor
            $table->string('role', 16)->default('owner');
            // pending | approved | rejected
            $table->string('status', 16)->default('pending');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['venue_id', 'user_id']);
            $table->index(['user_id', 'status']);
            $table->index(['venue_id', 'status']);
        });

        // Backfill every existing claim into a membership row so the
        // desk, policies, and the API all see the same source of
        // truth from day one. `claimed_by` stays as a shim for the
        // legacy VenuePolicy fallback until it is dropped later.
        $now = now();
        $approved = MembershipStatus::Approved->value;

        Venue::query()
            ->whereNotNull('claimed_by')
            ->get(['id', 'claimed_by', 'created_at'])
            ->each(function (Venue $venue) use ($now, $approved): void {
                DB::table('venue_user')->updateOrInsert(
                    ['venue_id' => $venue->id, 'user_id' => $venue->claimed_by],
                    [
                        'role' => 'owner',
                        'status' => $approved,
                        'requested_at' => $venue->created_at,
                        'approved_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_user');
    }
};
