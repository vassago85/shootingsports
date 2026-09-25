<?php

use App\Models\Provider;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        $logoPath = 'provider-logos/feather-fur-and-target.png';
        $source = database_path('seeders/data/ffat-logo.png');

        if (is_file($source)) {
            Storage::disk('media')->put($logoPath, (string) file_get_contents($source));
        }

        Provider::query()
            ->where('slug', 'feather-fur-and-target')
            ->update(['logo_path' => $logoPath]);
    }

    public function down(): void
    {
        Provider::query()
            ->where('slug', 'feather-fur-and-target')
            ->update(['logo_path' => null]);

        Storage::disk('media')->delete('provider-logos/feather-fur-and-target.png');
    }
};
