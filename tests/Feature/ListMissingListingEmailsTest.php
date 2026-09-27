<?php

use App\Enums\EventStatus;
use App\Enums\ListingStatus;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Provider;

it('lists clubs, businesses, and events that have no email', function () {
    Organisation::factory()->create([
        'name' => 'Listed Club',
        'email' => 'listed@club.test',
        'status' => ListingStatus::Published,
    ]);
    Organisation::factory()->create([
        'name' => 'Silent Club',
        'email' => null,
        'status' => ListingStatus::Published,
    ]);
    Provider::factory()->create([
        'name' => 'Silent Guns',
        'email' => '',
        'status' => ListingStatus::Published,
    ]);
    Provider::factory()->create([
        'name' => 'Reached Guns',
        'email' => 'shop@example.test',
        'status' => ListingStatus::Published,
    ]);
    Event::factory()->create([
        'title' => 'Silent Match',
        'contact_email' => null,
        'status' => EventStatus::Confirmed,
    ]);
    Event::factory()->create([
        'title' => 'Reached Match',
        'contact_email' => 'match@example.test',
        'status' => EventStatus::Confirmed,
    ]);

    $this->artisan('listings:missing-emails')
        ->expectsOutputToContain('Silent Club')
        ->expectsOutputToContain('Silent Guns')
        ->expectsOutputToContain('Silent Match')
        ->doesntExpectOutputToContain('Listed Club')
        ->doesntExpectOutputToContain('Reached Guns')
        ->doesntExpectOutputToContain('Reached Match')
        ->assertSuccessful();
});
