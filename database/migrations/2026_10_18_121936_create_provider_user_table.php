<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase B — the provider ↔ user pivot that replaces the implicit
 * `providers.claimed_by` scalar and any Claim rows pending against
 * providers. Same shape as `organisation_user` so the desk code can
 * reuse the same traits / membership trait on both sides.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // owner | editor
            $table->string('role', 16)->default('owner');
            // pending | approved | rejected
            $table->string('status', 16)->default('pending');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['provider_id', 'user_id']);
            $table->index(['user_id', 'status']);
            $table->index(['provider_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_user');
    }
};
