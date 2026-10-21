<?php

use App\Enums\Province;
use App\Livewire\Matches\SubmitMatch;
use App\Models\Discipline;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->withoutVite();
});

/**
 * These tests exercise the wizard flow itself — step gating, nav,
 * and per-step validation. End-to-end submission is covered by
 * {@see tests/Feature/MatchSubmissionTest.php}; the direct-submit
 * path there also proves the wizard class still saves correctly
 * when a caller sets every field and jumps straight to submit().
 */
it('starts on step one for a new submission', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(SubmitMatch::class)
        ->assertSet('step', 1);
});

it('refuses to advance past step one without a date', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(SubmitMatch::class)
        ->set('starts_on', '')
        ->call('next')
        ->assertHasErrors('starts_on')
        ->assertSee('Enter the date.')
        ->assertSet('step', 1);
});

it('refuses to advance past step one with a date in the past', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(SubmitMatch::class)
        ->set('starts_on', now()->subDay()->toDateString())
        ->call('next')
        ->assertHasErrors('starts_on')
        ->assertSee('The date must be today or later.')
        ->assertSet('step', 1);
});

it('demands start and end times when the all-day toggle is off', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(SubmitMatch::class)
        ->set('starts_on', now()->addWeek()->toDateString())
        ->set('all_day', false)
        ->set('starts_at_time', '')
        ->set('ends_at_time', '')
        ->call('next')
        ->assertHasErrors(['starts_at_time', 'ends_at_time'])
        ->assertSet('step', 1);
});

it('rejects an end time earlier than the start time', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(SubmitMatch::class)
        ->set('starts_on', now()->addWeek()->toDateString())
        ->set('all_day', false)
        ->set('starts_at_time', '14:00')
        ->set('ends_at_time', '09:00')
        ->call('next')
        ->assertHasErrors('ends_at_time')
        ->assertSee('The end time must be after the start time.')
        ->assertSet('step', 1);
});

it('advances step by step when each stage is clean', function () {
    $user = User::factory()->create();
    $discipline = Discipline::factory()->create();

    $component = Livewire::actingAs($user)
        ->test(SubmitMatch::class)
        ->set('starts_on', now()->addWeek()->toDateString())
        ->call('next')
        ->assertSet('step', 2)
        ->assertHasNoErrors()
        ->set('title', 'Wednesday IPSC')
        ->set('discipline_id', (string) $discipline->id)
        ->call('next')
        ->assertSet('step', 3)
        ->assertHasNoErrors()
        ->set('host_name', 'Pretoria Rifle Club')
        ->set('province', Province::Gauteng->value)
        ->call('next')
        ->assertSet('step', 4)
        ->assertHasNoErrors();

    expect($component->get('step'))->toBe(4);
});

it('blocks step two until the match has a title', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(SubmitMatch::class)
        ->set('starts_on', now()->addWeek()->toDateString())
        ->call('next')
        ->assertSet('step', 2)
        ->set('title', '')
        ->call('next')
        ->assertHasErrors('title')
        ->assertSet('step', 2);
});

it('blocks step three until the host and province are set', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(SubmitMatch::class)
        ->set('starts_on', now()->addWeek()->toDateString())
        ->call('next')
        ->set('title', 'Steel Challenge')
        ->call('next')
        ->assertSet('step', 3)
        ->set('host_name', '')
        ->set('province', '')
        ->call('next')
        ->assertHasErrors(['host_name', 'province'])
        ->assertSee('Enter the club, range or series.')
        ->assertSee('Pick a province.')
        ->assertSet('step', 3);
});

it('lets the user step back without re-validating', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(SubmitMatch::class)
        ->set('starts_on', now()->addWeek()->toDateString())
        ->call('next')
        ->set('title', 'Winter league')
        ->call('next')
        ->assertSet('step', 3)
        ->call('back')
        ->assertSet('step', 2)
        ->assertHasNoErrors()
        ->call('back')
        ->assertSet('step', 1);
});

it('drops a rejected time pair when all day is ticked again', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(SubmitMatch::class)
        ->set('starts_on', now()->addWeek()->toDateString())
        ->set('all_day', false)
        ->set('starts_at_time', '14:00')
        ->set('ends_at_time', '09:00')
        ->call('next')
        ->assertHasErrors('ends_at_time')
        ->assertSet('step', 1)
        ->set('all_day', true)
        ->assertSet('starts_at_time', '')
        ->assertSet('ends_at_time', '')
        ->call('next')
        ->assertHasNoErrors()
        ->assertSet('step', 2)
        ->assertSet('starts_on', now()->addWeek()->toDateString());
});

it('ignores times left over while the match is still all day', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(SubmitMatch::class)
        ->set('starts_on', now()->addWeek()->toDateString())
        ->set('starts_at_time', '14:00')
        ->set('ends_at_time', '09:00')
        ->call('next')
        ->assertHasNoErrors()
        ->assertSet('step', 2)
        ->assertSet('starts_at_time', '')
        ->assertSet('ends_at_time', '');
});

it('keeps the date when the title is filled in', function () {
    $user = User::factory()->create();
    $date = now()->addWeek()->toDateString();

    Livewire::actingAs($user)
        ->test(SubmitMatch::class)
        ->assertSeeHtml('wire:key="wizard-step-date"')
        ->set('starts_on', $date)
        ->call('next')
        ->assertSet('step', 2)
        ->assertSeeHtml('wire:key="wizard-step-match"')
        ->set('title', 'Wednesday IPSC')
        ->assertSet('title', 'Wednesday IPSC')
        ->assertSet('starts_on', $date);
});

it('refuses to open review from an empty form', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(SubmitMatch::class)
        ->call('goTo', 99)
        ->assertSet('step', 1)
        ->assertSee('When is the match?')
        ->call('goTo', -5)
        ->assertSet('step', 1)
        ->set('step', 4)
        ->assertSet('step', 1);
});

it('stops a skipped URL on the first incomplete step', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('events.submit', ['step' => 99]))
        ->assertRedirect(route('events.submit', ['step' => 1]));

    $this->actingAs($user)
        ->get(route('events.submit', ['step' => 1]))
        ->assertOk()
        ->assertSee('When is the match?')
        ->assertDontSee('Submit match');
});

it('allows review once every earlier step is valid', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(SubmitMatch::class)
        ->set('starts_on', now()->addWeek()->toDateString())
        ->set('title', 'Wednesday IPSC')
        ->set('host_name', 'Pretoria Rifle Club')
        ->set('province', Province::Gauteng->value)
        ->call('goTo', 99)
        ->assertSet('step', SubmitMatch::TOTAL_STEPS)
        ->assertSee('Does this look right?')
        ->assertSee('Wednesday IPSC')
        ->assertSee('Pretoria Rifle Club');
});

it('records step changes as browser history entries', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test(SubmitMatch::class);

    $effects = invade($component)->lastState->getEffects();

    expect(data_get($effects, 'url.step.use'))->toBe('push')
        ->and(data_get($effects, 'url.step.as'))->toBe('step');
});
