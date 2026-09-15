<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enquiries', function (Blueprint $table): void {
            // user_id links a waitlist / logged-in enquiry back to the
            // account. Anonymous /contact and /advertise submissions
            // stay nullable. nullOnDelete keeps the enquiry record if
            // the user is later removed — the waitlist metric should
            // survive account deletions.
            $table->foreignId('user_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();

            // Single payload column reused by both:
            //   pro_waitlist: { trigger: 'follow_limit', answer?: '...' }
            //   advertise:    { product: 'discover_placement' }
            // Keeps the schema flat — no per-type table sprawl for a
            // handful of extra fields per row.
            $table->json('context')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('enquiries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('context');
        });
    }
};
