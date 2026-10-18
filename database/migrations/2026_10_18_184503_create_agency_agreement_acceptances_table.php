<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agency_agreement_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('version');
            $table->string('ip_address')->nullable();
            $table->timestamps();

            $table->unique(['organisation_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_agreement_acceptances');
    }
};
