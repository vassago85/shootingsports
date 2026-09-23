<?php

use App\Models\User;

beforeEach(function () {
    $this->withoutVite();
});

it('shows unfinished calendar features to staff', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->get(route('calendar'))
        ->assertOk()
        ->assertSee('Still to build')
        ->assertSeeInOrder(['Firearm licence renewal reminders', 'Pro']);
});

it('hides unfinished calendar features from guests, shooters and match directors', function () {
    $this->get(route('calendar'))
        ->assertOk()
        ->assertDontSee('Still to build')
        ->assertDontSee('Firearm licence renewal reminders');

    $this->actingAs(User::factory()->create())
        ->get(route('calendar'))
        ->assertOk()
        ->assertDontSee('Firearm licence renewal reminders');

    $this->actingAs(User::factory()->matchDirector()->create())
        ->get(route('calendar'))
        ->assertOk()
        ->assertDontSee('Firearm licence renewal reminders');
});
