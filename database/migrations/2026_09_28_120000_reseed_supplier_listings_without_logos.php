<?php

use App\Models\Provider;
use Database\Seeders\SupplierDirectorySeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        (new SupplierDirectorySeeder)->run();
    }

    public function down(): void
    {
        Provider::query()
            ->whereIn('slug', SupplierDirectorySeeder::slugs())
            ->forceDelete();
    }
};
