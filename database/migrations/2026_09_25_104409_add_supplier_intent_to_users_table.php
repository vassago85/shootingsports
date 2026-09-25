<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('supplier_requested_at')->nullable()->after('md_rejection_reason');
            $table->string('pending_business_name', 160)->nullable()->after('supplier_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['supplier_requested_at', 'pending_business_name']);
        });
    }
};
