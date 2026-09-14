<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisations', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->string('type');
            $table->foreignId('parent_id')->nullable()->constrained('organisations')->nullOnDelete();
            $table->string('province')->nullable();
            $table->string('town')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website_url')->nullable();
            $table->string('facebook_url')->nullable();
            $table->text('description')->nullable();
            $table->boolean('accredited')->default(false);
            $table->boolean('visitors_welcome')->default(false);
            $table->string('status')->default('pending');
            $table->string('verification_state')->default('unconfirmed');
            $table->timestampTz('last_verified_at')->nullable();
            $table->string('verification_token')->nullable();
            $table->foreignId('claimed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source')->default('staff');
            $table->timestamps();
            $table->softDeletes();

            $table->index('province');
            $table->index('type');
            $table->index('verification_state');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisations');
    }
};
