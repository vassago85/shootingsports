<?php

use App\Models\Discipline;
use Database\Seeders\DisciplineSeeder;
use Illuminate\Support\Facades\Storage;

it('publishes a hero image for the Bisley & Fullbore discipline', function () {
    Storage::fake('media');

    $this->seed(DisciplineSeeder::class);

    $discipline = Discipline::query()->where('slug', 'bisley-fullbore')->first();

    expect($discipline)->not->toBeNull()
        ->and($discipline->image_path)->toBe('discipline-images/bisley-fullbore.jpg');

    Storage::disk('media')->assertExists('discipline-images/bisley-fullbore.jpg');
});

it('leaves a staff-curated hero image on a discipline alone', function () {
    Storage::fake('media');

    $this->seed(DisciplineSeeder::class);

    Discipline::query()
        ->where('slug', 'bisley-fullbore')
        ->update(['image_path' => 'discipline-images/custom-upload.jpg']);

    $this->seed(DisciplineSeeder::class);

    expect(Discipline::query()->where('slug', 'bisley-fullbore')->value('image_path'))
        ->toBe('discipline-images/custom-upload.jpg');
});
