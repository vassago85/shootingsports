<?php

use App\Models\Venue;

it('shows a range gallery and uses the first photo as the list cover', function () {
    $venue = Venue::factory()->create([
        'name' => 'Photo Range',
        'slug' => 'photo-range',
        'image_paths' => ['range-images/bay.jpg', 'range-images/clubhouse.jpg'],
    ]);

    $photos = $venue->imageUrls();

    $this->get(route('ranges.show', $venue->slug))
        ->assertOk()
        ->assertSee($photos[0], false)
        ->assertSee($photos[1], false);

    $this->get(route('ranges.index'))
        ->assertOk()
        ->assertSee($photos[0], false)
        ->assertDontSee($photos[1], false);
});

it('leaves the range page intact when there are no photos', function () {
    $venue = Venue::factory()->create([
        'name' => 'Bare Range',
        'slug' => 'bare-range',
    ]);

    $this->get(route('ranges.show', $venue->slug))
        ->assertOk()
        ->assertSee('Bare Range')
        ->assertDontSee('range-photos', false);
});
