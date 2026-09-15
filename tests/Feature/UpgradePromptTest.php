<?php

use App\Enums\EnquiryType;
use App\Enums\Plan;
use App\Livewire\FollowButton;
use App\Livewire\UpgradePrompt;
use App\Models\Enquiry;
use App\Models\Follow;
use App\Models\Organisation;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
});

it('records a single pro_waitlist enquiry per (user, trigger)', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(UpgradePrompt::class)
        ->dispatch('open-upgrade-prompt', trigger: 'follow_limit')
        ->assertSet('trigger', 'follow_limit')
        ->assertSet('open', true)
        ->set('answer', 'follow more than 3 clubs please')
        ->call('notify')
        ->assertSet('submitted', true);

    expect(Enquiry::query()->where('type', EnquiryType::ProWaitlist->value)->count())->toBe(1);

    $enquiry = Enquiry::query()->first();
    expect($enquiry->user_id)->toBe($user->id)
        ->and($enquiry->context['trigger'])->toBe('follow_limit')
        ->and($enquiry->context['answer'])->toBe('follow more than 3 clubs please');
});

it('does not duplicate rows when the same trigger is tapped twice', function () {
    $user = User::factory()->create();

    // First tap
    Livewire::actingAs($user)
        ->test(UpgradePrompt::class)
        ->dispatch('open-upgrade-prompt', trigger: 'follow_limit')
        ->set('answer', 'first')
        ->call('notify');

    // Second tap on the same trigger
    Livewire::actingAs($user)
        ->test(UpgradePrompt::class)
        ->dispatch('open-upgrade-prompt', trigger: 'follow_limit')
        ->set('answer', 'second')
        ->call('notify');

    expect(Enquiry::query()->where('type', EnquiryType::ProWaitlist->value)->count())->toBe(1);

    $enquiry = Enquiry::query()->first();
    // Second answer overwrites the first — one canonical demand row.
    expect($enquiry->context['answer'])->toBe('second');
});

it('records separate rows per trigger for the same user', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(UpgradePrompt::class)
        ->dispatch('open-upgrade-prompt', trigger: 'follow_limit')
        ->call('notify');

    Livewire::actingAs($user)
        ->test(UpgradePrompt::class)
        ->dispatch('open-upgrade-prompt', trigger: 'saved_search_limit')
        ->call('notify');

    expect(Enquiry::query()->where('type', EnquiryType::ProWaitlist->value)->count())->toBe(2);
});

it('does not open on layout mount / without an event', function () {
    Livewire::test(UpgradePrompt::class)
        ->assertSet('open', false)
        ->assertSet('trigger', '');
});

it('closes cleanly and resets state', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(UpgradePrompt::class)
        ->dispatch('open-upgrade-prompt', trigger: 'follow_limit')
        ->set('answer', 'yes please')
        ->call('close')
        ->assertSet('open', false)
        ->assertSet('trigger', '')
        ->assertSet('answer', '');
});

it('renders trigger-specific copy', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test(UpgradePrompt::class)
        ->dispatch('open-upgrade-prompt', trigger: 'history_window');

    $component->assertSee('Your Free calendar remembers the last 12 months.');
});

it('a guest sees a sign-in call to action instead of the notify button', function () {
    Livewire::test(UpgradePrompt::class)
        ->dispatch('open-upgrade-prompt', trigger: 'follow_limit')
        ->assertSee('Sign in to join the waitlist')
        ->assertDontSee('Notify me when Pro launches');
});

it('the follow observer causes a 4th follow to dispatch open-upgrade-prompt via FollowButton', function () {
    $user = User::factory()->create();

    $orgs = Organisation::factory()->count(4)->create();

    // Consume the free cap through the observer
    foreach ($orgs->take(3) as $org) {
        Follow::create([
            'user_id' => $user->id,
            'followable_type' => 'organisation',
            'followable_id' => $org->id,
        ]);
    }

    $fourth = $orgs->last();

    Livewire::actingAs($user)
        ->test(FollowButton::class, [
            'type' => 'organisation',
            'id' => $fourth->id,
        ])
        ->call('toggle')
        ->assertDispatched('open-upgrade-prompt', trigger: 'follow_limit');

    expect(Follow::query()->where('user_id', $user->id)->count())->toBe(3);
});

it('a pro user can add a 4th follow via FollowButton with no prompt', function () {
    $user = User::factory()->create(['plan' => Plan::Pro]);
    $orgs = Organisation::factory()->count(4)->create();

    foreach ($orgs->take(3) as $org) {
        Follow::create([
            'user_id' => $user->id,
            'followable_type' => 'organisation',
            'followable_id' => $org->id,
        ]);
    }

    $fourth = $orgs->last();

    Livewire::actingAs($user)
        ->test(FollowButton::class, [
            'type' => 'organisation',
            'id' => $fourth->id,
        ])
        ->call('toggle')
        ->assertNotDispatched('open-upgrade-prompt')
        ->assertSet('following', true);

    expect(Follow::query()->where('user_id', $user->id)->count())->toBe(4);
});
