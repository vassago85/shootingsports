<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flags', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('definition');
            $table->string('family');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_filterable')->default(true);
            $table->timestamps();
        });

        Schema::create('event_flag', function (Blueprint $table) {
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('flag_id')->constrained()->cascadeOnDelete();

            $table->primary(['event_id', 'flag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_flag');
        Schema::dropIfExists('flags');
    }
};
