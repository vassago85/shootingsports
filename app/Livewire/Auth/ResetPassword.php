<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

class ResetPassword extends Component
{
    #[Locked]
    public string $token = '';

    #[Validate('required|string|email|max:255')]
    public string $email = '';

    #[Validate('required|string|min:8|confirmed')]
    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = (string) request()->query('email', '');
    }

    public function resetPassword(): void
    {
        $this->validate();
        $this->ensureNotRateLimited();

        $status = Password::reset(
            [
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'token' => $this->token,
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'This reset link is invalid or has expired.',
            ]);
        }

        session()->flash('status', 'Your password has been reset. Sign in with the new password.');

        $this->redirect(route('login'), navigate: false);
    }

    protected function ensureNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('Too many reset attempts. Try again in :seconds seconds.', ['seconds' => $seconds]),
        ]);
    }

    protected function throttleKey(): string
    {
        return 'password-reset-submit|'.request()->ip();
    }

    public function render()
    {
        return view('livewire.auth.reset-password')
            ->layout('components.layouts.public', [
                'title' => 'Choose a new password',
                'robots' => 'noindex, nofollow',
            ]);
    }
}
