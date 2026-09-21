<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Public supplier signup (gunsmiths, dealers, optics, reloading — any
 * shooting-industry business).
 *
 * Unlike the match-director flow this is not staff-gated at the
 * account level: the applicant creates a shooter-shaped user, hits
 * "verify your email" straight away, and only sees the listing form
 * (see App\Livewire\Suppliers\CreateListing) after confirming the
 * address. The listing they then create is `pending` until staff
 * publish it — the public directory does not fill with unverified
 * "dealer" rows just because someone owned an inbox.
 *
 * Email verification is fired by the Registered event via
 * SendEmailVerificationNotification (wired in AppServiceProvider).
 */
class SupplierRegister extends Component
{
    #[Validate('required|string|max:120')]
    public string $name = '';

    #[Validate('required|string|max:160')]
    public string $business_name = '';

    #[Validate('required|string|email|max:255|unique:users,email')]
    public string $email = '';

    #[Validate('required|string|min:8|confirmed')]
    public string $password = '';

    #[Validate('required|string')]
    public string $password_confirmation = '';

    public function register(): void
    {
        $this->validate();

        $user = User::create([
            'name' => trim($this->name),
            'email' => strtolower(trim($this->email)),
            'password' => Hash::make($this->password),
            'is_staff' => false,
            'is_match_director' => false,
        ]);

        // The listing form reads this out of the session on first
        // render so the applicant does not have to retype their
        // business name — they typed it during signup. Cleared once
        // the listing is created.
        Session::put('supplier.pending_business_name', trim($this->business_name));

        event(new Registered($user));

        Auth::login($user);
        Session::regenerate();

        $this->redirect(route('verification.notice'), navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.supplier-register')
            ->layout('components.layouts.public', ['title' => 'Register as a supplier']);
    }
}
