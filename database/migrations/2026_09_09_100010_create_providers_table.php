<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('category');
            $table->string('province');
            $table->string('town');
            $table->string('metro')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website_url')->nullable();
            $table->text('description')->nullable();
            $table->string('tier')->default('free');
            $table->string('status')->default('pending');
            $table->string('verification_state')->default('unconfirmed');
            $table->timestampTz('last_verified_at')->nullable();
            $table->string('verification_token')->nullable();
            $table->foreignId('claimed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source')->default('staff');
            $table->timestamps();
            $table->softDeletes();

            $table->index('province');
            $table->index('category');
            $table->index('verification_state');
            $table->index(['lat', 'lng']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};
