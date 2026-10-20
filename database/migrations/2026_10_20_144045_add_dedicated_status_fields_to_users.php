<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dedicated Status Tracker: three user-entered fields that drive the
 * Pro-only renewal-activity widget on /me/log and /me.
 *
 * We deliberately do NOT hardcode any accrediting body (SAHGCA, SAGA,
 * SAPSA, SAPRF, etc.) or their required counts. Each association sets
 * its own rules and those rules move; a hardcoded list is a
 * maintenance trap. The shooter enters their own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('dedicated_body')->nullable()->after('home_province');
            $table->date('dedicated_renewal_on')->nullable()->after('dedicated_body');
            $table->unsignedSmallInteger('dedicated_required_count')->nullable()->after('dedicated_renewal_on');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['dedicated_body', 'dedicated_renewal_on', 'dedicated_required_count']);
        });
    }
};
