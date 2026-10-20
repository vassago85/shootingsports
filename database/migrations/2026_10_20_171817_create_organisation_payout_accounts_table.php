<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisation_payout_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('draft');
            $table->string('paystack_subaccount_code')->nullable();
            $table->string('account_holder_name');
            $table->string('bank_code');
            $table->string('bank_name');
            $table->boolean('bank_enabled_for_verification')->default(false);
            // Ciphertext is longer than the account number, so this is text.
            $table->text('account_number');
            $table->string('account_last_four', 4);
            $table->string('account_type');
            $table->string('document_type');
            $table->text('document_number');
            $table->boolean('validation_verified')->nullable();
            $table->text('validation_message')->nullable();
            $table->json('validation_checks')->nullable();
            $table->timestamp('validation_checked_at')->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->string('terms_version')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique('organisation_id');
            $table->unique('paystack_subaccount_code');
            $table->index(['status', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisation_payout_accounts');
    }
};
