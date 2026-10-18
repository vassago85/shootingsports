<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_invoices', function (Blueprint $table): void {
            $table->unsignedInteger('platform_fee_cents')->default(0);
            $table->unsignedInteger('platform_fee_vat_cents')->default(0);
            $table->unsignedInteger('gateway_fee_cents')->default(0);
            $table->unsignedInteger('net_to_club_cents')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('membership_invoices', function (Blueprint $table): void {
            $table->dropColumn([
                'platform_fee_cents',
                'platform_fee_vat_cents',
                'gateway_fee_cents',
                'net_to_club_cents',
            ]);
        });
    }
};
