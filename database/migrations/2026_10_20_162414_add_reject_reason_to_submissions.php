<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores the reason staff supply when rejecting a Submission from the
 * admin Inbox. Visible on the record page so a later moderator sees
 * WHY an older submission was closed; not emailed to the submitter
 * (that would turn every rejection into a support conversation).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table): void {
            $table->text('reject_reason')->nullable()->after('moderator_id');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table): void {
            $table->dropColumn('reject_reason');
        });
    }
};
