<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional accrediting-body membership number. Rendered at the top of
 * the annual attendance PDF the shooter takes to their association
 * for dedicated-status renewal.
 *
 * Free text on purpose — SAPSA numbers, PISA numbers, KKSA numbers
 * and the informal "member #123" strings clubs still use all live in
 * the same column. Validation costs more than it earns here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('association_membership_number')->nullable()->after('digest_frequency');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('association_membership_number');
        });
    }
};
