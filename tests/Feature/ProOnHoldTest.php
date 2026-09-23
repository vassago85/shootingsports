<?php

use App\Enums\Plan;
use App\Livewire\Upgrade;
use App\Livewire\UpgradePrompt;
use App\Models\User;
use App\Services\StartProTrial;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    config(['plans.pro_enabled' => false]);
});

it('hides Pro sales on signup and the upgrade page', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertDontSee('30-day Pro trial');

    $user = User::factory()->create(['plan' => Plan::Free, 'plan_expires_at' => null]);

    Livewire::actingAs($user)
        ->test(Upgrade::class)
        ->assertSee('Pro is on hold')
        ->assertDontSee('Start my 30-day trial')
        ->assertDontSee('Continue to secure checkout')
        ->call('startTrial')
        ->call('checkout');

    expect($user->fresh()->isOnTrial())->toBeFalse()
        ->and(StartProTrial::for($user->fresh()))->toBeFalse();
});

it('explains a free limit without a buy button', function () {
    $user = User::factory()->create(['plan' => Plan::Free]);

    Livewire::actingAs($user)
        ->test(UpgradePrompt::class)
        ->dispatch('open-upgrade-prompt', trigger: 'follow_limit')
        ->assertSee('Pro is on hold')
        ->assertDontSee('Go Pro now')
        ->assertDontSee('Notify me when Pro launches')
        ->assertDontSee('R29');
});
