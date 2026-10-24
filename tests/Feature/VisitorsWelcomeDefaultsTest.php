<?php

use App\Models\Organisation;

beforeEach(function (): void {
    $this->withoutVite();
});

it('defaults a freshly created organisation to visitors_welcome = null', function () {
    $organisation = Organisation::factory()->create();

    expect($organisation->visitors_welcome)->toBeNull();
});

it('serializes visitors_welcome as null in the organisations API when not set', function () {
    Organisation::factory()->create(['name' => 'Nullable Club']);

    $row = $this->getJson(route('api.v1.organisations.index'))
        ->assertOk()
        ->json('data.0');

    expect($row)->toHaveKey('visitors_welcome');
    expect($row['visitors_welcome'])->toBeNull();
});

it('preserves an explicit true from the admin form', function () {
    $organisation = Organisation::factory()->create(['visitors_welcome' => true]);

    expect($organisation->fresh()->visitors_welcome)->toBeTrue();
});
