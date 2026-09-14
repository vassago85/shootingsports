<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('province');
            $table->string('town');
            $table->string('metro')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->text('address')->nullable();
            $table->unsignedInteger('max_distance_m')->nullable();
            $table->unsignedInteger('bay_count')->nullable();
            $table->string('access')->default('guest_by_arrangement');
            $table->unsignedInteger('day_fee_cents')->nullable();
            $table->jsonb('facilities')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('pending');
            $table->string('verification_state')->default('unconfirmed');
            $table->timestampTz('last_verified_at')->nullable();
            $table->string('verification_token')->nullable();
            $table->foreignId('claimed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source')->default('staff');
            $table->timestamps();
            $table->softDeletes();

            $table->index('province');
            $table->index(['lat', 'lng']);
            $table->index('verification_state');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venues');
    }
};
