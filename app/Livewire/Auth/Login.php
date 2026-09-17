<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Unified public login. Post-auth redirect uses the user's own role
 * (staff → /admin, MD → /desk, shooter → /my-calendar) unless an
 * intended URL was captured by the auth middleware, in which case we
 * honour that.
 *
 * Rate-limited per (email, ip) so credential stuffing does not just
 * hammer the endpoint. Matches the throttle Laravel's own Fortify
 * uses out of the box.
 */
class Login extends Component
{
    #[Validate('required|string|email|max:255')]
    public string $email = '';

    #[Validate('required|string|max:255')]
    public string $password = '';

    public bool $remember = false;

    public function authenticate(): void
    {
        $this->validate();
        $this->ensureNotRateLimited();

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('These credentials do not match our records.'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        // Session::regenerate() rather than request()->session()->regenerate()
        // — the facade resolves the store through the SessionManager, which
        // is always available in the container. Direct request access would
        // require StartSession middleware to have run for this request.
        Session::regenerate();

        $user = Auth::user();
        $default = $user instanceof User ? $user->defaultRedirectPath() : '/';

        // While the public site is gated behind /coming-soon, plain
        // shooters have nowhere useful to land — their /my-calendar
        // page would just bounce back through EnsureComingSoonAccess.
        // Send them straight to the landing page instead so the UX
        // is one hop, not a redirect chain.
        if (
            config('coming-soon.enabled')
            && $user instanceof User
            && ! $user->is_staff
            && ! $user->is_match_director
        ) {
            $default = route('coming-soon');
        }

        $this->redirectIntended(default: $default, navigate: false);
    }

    protected function ensureNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('Too many login attempts. Try again in :seconds seconds.', ['seconds' => $seconds]),
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }

    public function render()
    {
        return view('livewire.auth.login')
            ->layout('components.layouts.public', ['title' => 'Log in']);
    }
}
