<?php

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

it('issues a sanctum token on valid login', function (): void {
    $user = User::factory()->create([
        'email' => 'shooter@example.com',
        'password' => bcrypt('correct-horse'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'shooter@example.com',
        'password' => 'correct-horse',
        'device_name' => 'iPhone 15',
    ])
        ->assertOk()
        ->assertJsonStructure(['token', 'device_name', 'user' => ['id', 'email', 'calendar_slug']]);

    expect($response->json('token'))->toBeString()->not->toBeEmpty()
        ->and($user->fresh()->tokens()->where('name', 'iPhone 15')->exists())->toBeTrue();
});

it('rejects invalid credentials with a 422', function (): void {
    User::factory()->create([
        'email' => 'shooter@example.com',
        'password' => bcrypt('correct-horse'),
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'shooter@example.com',
        'password' => 'nope',
    ])->assertStatus(422);
});

it('returns the current user for a valid bearer token', function (): void {
    $user = User::factory()->create(['name' => 'Jane Shooter']);
    $token = $user->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.name', 'Jane Shooter');
});

it('refuses /api/v1/me without a token', function (): void {
    $this->getJson('/api/v1/me')->assertStatus(401);
});

it('revokes the current token on logout', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/auth/logout')
        ->assertOk();

    // The token row is gone from the DB — verifying auth state in a
    // follow-up request within the same test would false-pass because
    // the RequestGuard singleton caches the resolved user across
    // requests (a test-only artefact, not a production behaviour).
    expect(PersonalAccessToken::count())->toBe(0);
});

it('rejects a bearer token that is not in the database', function (): void {
    $this->withHeader('Authorization', 'Bearer 999|nonexistent-token-value')
        ->getJson('/api/v1/me')
        ->assertStatus(401);
});
