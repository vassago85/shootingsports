<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->string('kind');
            $table->string('status')->default('pending');
            $table->unsignedInteger('amount_cents');
            $table->text('reason');
            $table->string('paystack_refund_id')->nullable();
            $table->boolean('club_share_settled')->default(false);
            $table->unsignedInteger('club_owes_cents')->default(0);
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('raw_response')->nullable();
            $table->timestamps();

            $table->index('payment_id');
            $table->index('organisation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_refunds');
    }
};
