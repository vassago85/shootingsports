<?php

use App\Enums\EventStatus;
use App\Enums\ListingSource;
use App\Models\Event;
use App\Models\User;

beforeEach(function () {
    $this->withoutVite();
});

it('shows the register dashboard with later staff tools still linked', function () {
    $staff = User::factory()->create(['is_staff' => true]);
    Event::factory()->create([
        'title' => 'Waiting Club Shoot',
        'status' => EventStatus::Draft,
        'confirmed_at' => null,
        'source' => ListingSource::Submission,
    ]);

    $this->actingAs($staff)
        ->get('/admin')
        ->assertOk()
        ->assertSee('What needs doing on the register')
        ->assertSee('Needs attention')
        ->assertSee('Waiting Club Shoot')
        ->assertSee('Email log')
        ->assertSee('Page pictures')
        ->assertSee('Submitted matches');
});
