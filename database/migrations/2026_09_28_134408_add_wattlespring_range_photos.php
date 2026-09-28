<?php

use App\Models\Venue;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table): void {
            $table->string('logo_path')->nullable()->after('image_paths');
        });

        $photos = [
            'wattlespring-restaurant.png',
            'wattlespring-restaurant-patio.png',
            'wattlespring-restaurant-oven.png',
            'wattlespring-shotgun-line.png',
            'wattlespring-shotgun.png',
            'wattlespring-rifle-range.png',
        ];

        $venue = Venue::withTrashed()->where('slug', 'wattlespring-sports-shooting-club')->first();

        if ($venue === null) {
            return;
        }

        $venue->update([
            'image_paths' => array_map(
                fn (string $filename): string => $this->store($filename, 'range-images'),
                $photos,
            ),
            'logo_path' => $this->store('wattlespring-logo.png', 'range-logos'),
        ]);

        if ($venue->trashed()) {
            $venue->restore();
        }
    }

    public function down(): void
    {
        $venue = Venue::withTrashed()->where('slug', 'wattlespring-sports-shooting-club')->first();

        if ($venue !== null) {
            foreach (array_merge((array) $venue->image_paths, [$venue->logo_path]) as $path) {
                if (is_string($path) && str_starts_with($path, 'range-')) {
                    Storage::disk('media')->delete($path);
                }
            }

            $venue->update([
                'image_paths' => null,
                'logo_path' => null,
            ]);
        }

        Schema::table('venues', function (Blueprint $table): void {
            $table->dropColumn('logo_path');
        });
    }

    private function store(string $filename, string $directory): string
    {
        $path = $directory.'/'.$filename;
        $source = database_path('seeders/data/'.$filename);

        if (is_file($source) && ! Storage::disk('media')->exists($path)) {
            Storage::disk('media')->put($path, file_get_contents($source));
        }

        return $path;
    }
};
