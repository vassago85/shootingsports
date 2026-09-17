<?php

use App\Models\DeviceToken;
use App\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('test')->plainTextToken;
});

it('registers a fresh Expo push token for the current user', function (): void {
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/v1/me/device-tokens', [
            'token' => 'ExponentPushToken[abc123]',
            'platform' => 'ios',
        ])
        ->assertCreated()
        ->assertJson(['platform' => 'ios', 'created' => true]);

    expect(DeviceToken::query()->where('token', 'ExponentPushToken[abc123]')->count())->toBe(1);
});

it('updates the existing row when the same token registers again', function (): void {
    DeviceToken::query()->create([
        'user_id' => $this->user->id,
        'token' => 'ExponentPushToken[abc123]',
        'platform' => 'android',
        'last_seen_at' => now()->subDay(),
    ]);

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/v1/me/device-tokens', [
            'token' => 'ExponentPushToken[abc123]',
            'platform' => 'android',
        ])
        ->assertOk()
        ->assertJson(['created' => false]);

    expect(DeviceToken::query()->where('token', 'ExponentPushToken[abc123]')->count())->toBe(1);
});

it('rejects an unknown platform value', function (): void {
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/v1/me/device-tokens', [
            'token' => 'ExponentPushToken[abc123]',
            'platform' => 'blackberry',
        ])
        ->assertStatus(422);
});

it('lets the shooter deregister their token on the same device', function (): void {
    DeviceToken::query()->create([
        'user_id' => $this->user->id,
        'token' => 'ExponentPushToken[xyz]',
        'platform' => 'ios',
    ]);

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson('/api/v1/me/device-tokens', ['token' => 'ExponentPushToken[xyz]'])
        ->assertOk()
        ->assertJson(['deleted' => 1]);

    expect(DeviceToken::query()->where('token', 'ExponentPushToken[xyz]')->count())->toBe(0);
});

it('refuses device-token registration without a token', function (): void {
    $this->postJson('/api/v1/me/device-tokens', [
        'token' => 'ExponentPushToken[abc]',
        'platform' => 'ios',
    ])->assertStatus(401);
});
