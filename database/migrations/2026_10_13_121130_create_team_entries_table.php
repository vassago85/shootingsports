<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('team_name', 120);
            $table->string('captain_name', 120);
            $table->string('captain_email');
            $table->string('captain_phone', 40);
            $table->text('members');
            $table->string('proof_of_payment_path')->nullable();
            $table->string('proof_of_payment_name')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['event_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_entries');
    }
};
