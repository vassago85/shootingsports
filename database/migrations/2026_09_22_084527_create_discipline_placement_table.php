<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discipline_placement', function (Blueprint $table) {
            $table->foreignId('discipline_id')->constrained()->cascadeOnDelete();
            $table->foreignId('placement_id')->constrained()->cascadeOnDelete();
            $table->primary(['discipline_id', 'placement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline_placement');
    }
};
