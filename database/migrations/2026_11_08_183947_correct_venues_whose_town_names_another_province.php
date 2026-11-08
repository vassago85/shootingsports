<?php

use App\Support\VenueProvinceCorrection;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        VenueProvinceCorrection::apply();
    }

    public function down(): void
    {
        // Province corrections are not reversible without the old value.
    }
};
