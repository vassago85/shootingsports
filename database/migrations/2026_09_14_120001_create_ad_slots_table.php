<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_slots', function (Blueprint $table) {
            $table->id();
            $table->string('page');
            $table->string('slot');
            $table->string('name');
            $table->unsignedInteger('price_cents')->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['page', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_slots');
    }
};
