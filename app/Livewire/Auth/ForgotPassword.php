<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Component;

class ForgotPassword extends Component
{
    #[Validate('required|string|email|max:255')]
    public string $email = '';

    public string $status = '';

    public function sendResetLink(): void
    {
        $this->validate();
        $this->ensureNotRateLimited();

        RateLimiter::hit($this->throttleKey());

        // The same reply whether or not the address is registered.
        Password::sendResetLink(['email' => $this->email]);

        $this->status = 'If an account exists for that email, we have sent a password reset link.';
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
        return 'password-reset|'.request()->ip();
    }

    public function render()
    {
        return view('livewire.auth.forgot-password')
            ->layout('components.layouts.public', [
                'title' => 'Reset password',
                'robots' => 'noindex, nofollow',
            ]);
    }
}
