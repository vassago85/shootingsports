<?php

use App\Models\Provider;
use Database\Seeders\SupplierDirectorySeeder;

it('restores a removed shop listing instead of inserting a duplicate slug', function () {
    $removed = Provider::query()->where('slug', 'feather-fur-and-target')->firstOrFail();
    $removed->update([
        'name' => 'Removed shop',
        'logo_path' => 'provider-logos/feather-fur-and-target.png',
    ]);
    $removed->delete();

    $this->seed(SupplierDirectorySeeder::class);
    $this->seed(SupplierDirectorySeeder::class);

    $listing = Provider::query()->where('slug', 'feather-fur-and-target')->first();

    expect($listing)->not->toBeNull()
        ->and($listing->id)->toBe($removed->id)
        ->and($listing->name)->toBe('Feather Fur and Target')
        ->and($listing->logo_path)->toBeNull()
        ->and(Provider::withTrashed()->where('slug', 'feather-fur-and-target')->count())->toBe(1);
});
