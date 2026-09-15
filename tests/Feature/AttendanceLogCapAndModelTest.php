<?php

use App\Enums\Plan;
use App\Exceptions\PlanLimitExceeded;
use App\Models\AttendedEvent;
use App\Models\User;

it('lets a Free user log up to 3 attendances', function () {
    $user = User::factory()->create(['plan' => Plan::Free]);

    AttendedEvent::factory()->count(3)->create(['user_id' => $user->id]);

    expect(AttendedEvent::query()->where('user_id', $user->id)->count())->toBe(3);
});

it('blocks a Free users 4th attendance with PlanLimitExceeded', function () {
    $user = User::factory()->create(['plan' => Plan::Free]);
    AttendedEvent::factory()->count(3)->create(['user_id' => $user->id]);

    expect(fn () => AttendedEvent::factory()->create(['user_id' => $user->id]))
        ->toThrow(PlanLimitExceeded::class);

    expect(AttendedEvent::query()->where('user_id', $user->id)->count())->toBe(3);
});

it('lets a Pro user log an unlimited number of attendances', function () {
    $user = User::factory()->create(['plan' => Plan::Pro]);

    AttendedEvent::factory()->count(25)->create(['user_id' => $user->id]);

    expect(AttendedEvent::query()->where('user_id', $user->id)->count())->toBe(25);
});

it('exposes the user->attendedEvents() relationship', function () {
    $user = User::factory()->create(['plan' => Plan::Pro]);
    AttendedEvent::factory()->count(2)->create(['user_id' => $user->id]);

    expect($user->attendedEvents)->toHaveCount(2);
});
