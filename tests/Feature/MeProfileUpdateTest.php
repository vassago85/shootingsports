<?php

use App\Enums\Province;
use App\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'home_province' => Province::Gauteng,
        'extra_provinces' => null,
        'travel_radius_km' => 100,
    ]);
    $this->token = $this->user->createToken('test')->plainTextToken;
});

it('exposes home_province, extra_provinces and travel_radius_km on GET /me', function (): void {
    $this->user->update(['extra_provinces' => ['western_cape', 'kwazulu_natal']]);

    $json = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->json('data');

    expect($json['home_province'])->toBe('Gauteng')
        ->and($json['home_province_slug'])->toBe('gauteng')
        ->and($json['travel_radius_km'])->toBe(100)
        ->and($json['extra_provinces'])->toEqual([
            ['label' => 'Western Cape', 'slug' => 'western-cape'],
            ['label' => 'KwaZulu-Natal', 'slug' => 'kwazulu-natal'],
        ]);
});

it('updates home_province and extra_provinces via PATCH /me', function (): void {
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->patchJson('/api/v1/me', [
            'home_province' => 'western_cape',
            'extra_provinces' => ['gauteng', 'free_state'],
            'travel_radius_km' => 250,
        ])
        ->assertOk()
        ->assertJsonPath('data.home_province_slug', 'western-cape')
        ->assertJsonPath('data.travel_radius_km', 250)
        ->assertJsonPath('data.extra_provinces.0.slug', 'gauteng')
        ->assertJsonPath('data.extra_provinces.1.slug', 'free-state');

    $this->user->refresh();

    expect($this->user->home_province)->toBe(Province::WesternCape)
        ->and($this->user->extra_provinces)->toBe(['gauteng', 'free_state'])
        ->and($this->user->travel_radius_km)->toBe(250);
});

it('accepts URL-slug province variants for home and extras', function (): void {
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->patchJson('/api/v1/me', [
            'home_province' => 'kwazulu-natal',
            'extra_provinces' => ['north-west'],
        ])
        ->assertOk();

    $this->user->refresh();

    expect($this->user->home_province)->toBe(Province::KwaZuluNatal)
        ->and($this->user->extra_provinces)->toBe(['north_west']);
});

it('clears extra_provinces when an empty array is supplied', function (): void {
    $this->user->update(['extra_provinces' => ['gauteng']]);

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->patchJson('/api/v1/me', ['extra_provinces' => []])
        ->assertOk();

    expect($this->user->refresh()->extra_provinces)->toBeNull();
});

it('clears home_province when null is supplied', function (): void {
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->patchJson('/api/v1/me', ['home_province' => null])
        ->assertOk();

    expect($this->user->refresh()->home_province)->toBeNull();
});

it('rejects an unknown province value with 422', function (): void {
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->patchJson('/api/v1/me', ['home_province' => 'mordor'])
        ->assertStatus(422);
});

it('silently drops unknown values inside extra_provinces', function (): void {
    // The mobile client should always send valid slugs. If it ever
    // sends a mix, we keep the valid ones and drop the junk — the
    // alternative is a 422 that blocks a legitimate first province
    // save because of a stale enum on the client.
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->patchJson('/api/v1/me', ['extra_provinces' => ['gauteng', 'atlantis']])
        ->assertOk();

    expect($this->user->refresh()->extra_provinces)->toBe(['gauteng']);
});

it('deduplicates duplicate provinces in extra_provinces', function (): void {
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->patchJson('/api/v1/me', [
            'extra_provinces' => ['gauteng', 'gauteng', 'western-cape'],
        ])
        ->assertOk();

    expect($this->user->refresh()->extra_provinces)->toBe(['gauteng', 'western_cape']);
});

it('requires authentication for PATCH /me', function (): void {
    $this->patchJson('/api/v1/me', ['home_province' => 'gauteng'])
        ->assertStatus(401);
});
