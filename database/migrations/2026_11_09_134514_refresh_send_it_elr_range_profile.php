<?php

use App\Support\Venues\CorrectSendItElrRange;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(CorrectSendItElrRange::class)();
    }

    public function down(): void
    {
        //
    }
};
