<?php

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
});

it('lets an unverified user request another confirmation link', function () {
    $user = User::factory()->unverified()->create();

    Notification::fake();

    $this->actingAs($user)
        ->post(route('verification.send'))
        ->assertRedirect()
        ->assertSessionHas('status', 'verification-link-sent');

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('shows a request-another prompt when the confirmation link has expired', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->subMinute(),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->actingAs($user)
        ->get($url)
        ->assertRedirect(route('verification.notice'));

    $this->actingAs($user)
        ->get(route('verification.notice'))
        ->assertOk()
        ->assertSee('That confirmation link has expired')
        ->assertSee('Request another link');

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('offers a way to request another link on the confirmation page', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('verification.notice'))
        ->assertOk()
        ->assertSee('request another link', false)
        ->assertSee('Request another link');
});

it('sends a fresh confirmation email from the users list for someone still waiting', function () {
    $user = User::factory()->unverified()->create();
    $staff = User::factory()->staff()->create();

    Notification::fake();

    Livewire::actingAs($staff)
        ->test(ListUsers::class)
        ->callTableAction('resendVerification', $user);

    Notification::assertSentTo($user, VerifyEmail::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('hides resend confirmation on the users list once the email is confirmed', function () {
    $user = User::factory()->create();
    $staff = User::factory()->staff()->create();

    Livewire::actingAs($staff)
        ->test(ListUsers::class)
        ->assertTableActionHidden('resendVerification', $user);
});
