<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table): void {
            $table->string('paystack_subaccount_id')->nullable()->unique();
            $table->string('paystack_subaccount_status')->nullable();
            $table->string('payout_method')->default('paystack_subaccount');
            $table->timestamp('agency_agreement_signed_at')->nullable();
            $table->string('agency_agreement_version')->nullable();
            $table->unsignedInteger('active_member_count')->default(0);
            $table->date('tier_grace_ends_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table): void {
            $table->dropUnique(['paystack_subaccount_id']);
            $table->dropColumn([
                'paystack_subaccount_id',
                'paystack_subaccount_status',
                'payout_method',
                'agency_agreement_signed_at',
                'agency_agreement_version',
                'active_member_count',
                'tier_grace_ends_at',
            ]);
        });
    }
};
