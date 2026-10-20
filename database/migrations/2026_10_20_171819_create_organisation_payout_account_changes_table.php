<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisation_payout_account_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_payout_account_id')
                ->constrained(indexName: 'payout_changes_account_fk')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('old_last_four', 4)->nullable();
            $table->string('new_last_four', 4)->nullable();
            $table->string('old_bank_name')->nullable();
            $table->string('new_bank_name')->nullable();
            $table->string('old_account_holder')->nullable();
            $table->string('new_account_holder')->nullable();
            $table->string('old_status')->nullable();
            $table->string('new_status')->nullable();
            $table->timestamps();

            $table->index('organisation_payout_account_id', 'payout_changes_account_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisation_payout_account_changes');
    }
};
