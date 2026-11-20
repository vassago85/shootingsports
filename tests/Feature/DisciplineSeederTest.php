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

it('publishes a backing image for the Action / Defensive discipline', function () {
    Storage::fake('media');

    $this->seed(DisciplineSeeder::class);

    $discipline = Discipline::query()->where('slug', 'action-defensive')->first();

    expect($discipline)->not->toBeNull()
        ->and($discipline->image_path)->toBe('discipline-images/action-defensive.jpg');

    Storage::disk('media')->assertExists('discipline-images/action-defensive.jpg');

    $this->get(route('disciplines.show', 'action-defensive'))
        ->assertOk()
        ->assertSee('has-photo', false)
        ->assertSee('discipline-images/action-defensive.jpg', false);
});

it('publishes a backing image for the gong shoot discipline', function () {
    Storage::fake('media');

    $this->seed(DisciplineSeeder::class);

    $discipline = Discipline::query()->where('slug', 'gong-shooting')->first();

    expect($discipline)->not->toBeNull()
        ->and($discipline->image_path)->toBe('discipline-images/gong-shooting.jpg');

    Storage::disk('media')->assertExists('discipline-images/gong-shooting.jpg');

    $this->get(route('disciplines.show', 'gong-shooting'))
        ->assertOk()
        ->assertSee('has-photo', false)
        ->assertSee('discipline-images/gong-shooting.jpg', false);
});

it('shares the valley photograph across the long-range rifle disciplines', function () {
    Storage::fake('media');

    $this->seed(DisciplineSeeder::class);

    $slugs = ['elr', '22lr-elr', 'f-class', 'target-rifle', 'precision-rifle', 'pr22-rimfire', 'nrl-hunter'];

    expect(Discipline::query()->whereIn('slug', $slugs)->pluck('image_path', 'slug')->all())
        ->toEqual(array_fill_keys($slugs, 'discipline-images/long-range.jpg'));

    Storage::disk('media')->assertExists('discipline-images/long-range.jpg');

    $this->get(route('disciplines.show', 'elr'))
        ->assertOk()
        ->assertSee('has-photo', false)
        ->assertSee('discipline-images/long-range.jpg', false);
});

it('publishes a backing image for the Multigun discipline', function () {
    Storage::fake('media');

    $this->seed(DisciplineSeeder::class);

    $discipline = Discipline::query()->where('slug', 'multigun')->first();

    expect($discipline)->not->toBeNull()
        ->and($discipline->image_path)->toBe('discipline-images/multigun.jpg');

    Storage::disk('media')->assertExists('discipline-images/multigun.jpg');

    $this->get(route('disciplines.show', 'multigun'))
        ->assertOk()
        ->assertSee('has-photo', false)
        ->assertSee('discipline-images/multigun.jpg', false);
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
