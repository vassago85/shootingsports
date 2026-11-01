<?php

use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\ResetPassword;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function (): void {
    RateLimiter::clear('password-reset|127.0.0.1');
    RateLimiter::clear('password-reset-submit|127.0.0.1');
});

it('shows a forgot-password link on the sign-in page', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Forgot password?', false)
        ->assertSee(route('password.request'), false);
});

it('sends a reset link without saying whether the email is registered', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'shooter@example.test']);
    $message = 'If an account exists for that email, we have sent a password reset link.';

    Livewire::test(ForgotPassword::class)
        ->set('email', 'missing@example.test')
        ->call('sendResetLink')
        ->assertSee($message);

    Notification::assertNothingSent();

    Livewire::test(ForgotPassword::class)
        ->set('email', $user->email)
        ->call('sendResetLink')
        ->assertSee($message);

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

it('stops accepting reset requests after five attempts', function () {
    Notification::fake();

    $component = Livewire::test(ForgotPassword::class)->set('email', 'missing@example.test');

    foreach (range(1, 5) as $attempt) {
        $component->call('sendResetLink');
    }

    $component->call('sendResetLink')
        ->assertHasErrors('email');

    Notification::assertNothingSent();
});

it('resets the password from a valid token and rejects a bad one', function () {
    $user = User::factory()->create(['email' => 'shooter@example.test']);
    $token = Password::createToken($user);

    Livewire::test(ResetPassword::class, ['token' => 'not-a-real-token'])
        ->set('email', $user->email)
        ->set('password', 'new-password-1')
        ->set('password_confirmation', 'new-password-1')
        ->call('resetPassword')
        ->assertHasErrors('email');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();

    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', $user->email)
        ->set('password', 'new-password-1')
        ->set('password_confirmation', 'new-password-1')
        ->call('resetPassword')
        ->assertRedirect(route('login'));

    expect(Hash::check('new-password-1', $user->fresh()->password))->toBeTrue();
});
