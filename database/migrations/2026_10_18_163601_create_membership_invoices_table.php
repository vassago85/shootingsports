<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_member_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('amount_cents');
            // Status strings match MembershipInvoiceStatus enum; stored
            // as string so adding statuses stays a code change only.
            $table->string('status');
            $table->timestampTz('issued_at')->nullable();
            $table->date('due_at')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('payment_pending_confirmation_at')->nullable();
            // Payment method strings match MembershipInvoicePaymentMethod.
            $table->string('payment_method')->nullable();
            $table->string('paystack_reference')->nullable()->index();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            // One invoice per member/period keeps the batch issuer
            // idempotent and makes "did we already bill Alice for 2026?"
            // a one-row lookup instead of a GROUP BY.
            $table->unique(['club_member_id', 'period_start', 'period_end'], 'membership_invoices_member_period_unique');
            $table->index(['organisation_id', 'status']);
            $table->index(['organisation_id', 'period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_invoices');
    }
};
