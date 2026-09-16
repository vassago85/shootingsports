<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Services\StartProTrial;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Public shooter signup. Minimal fields (name / email / password),
 * lands the user on /my-calendar. Creates a plain account — no MD
 * flag, no staff flag, default Free plan.
 *
 * Shooters cannot become match directors from this page. If they
 * change their mind later they can register a second account via
 * /directors/register, or a staff member can flip the flag from
 * the admin UserResource.
 */
class ShooterRegister extends Component
{
    #[Validate('required|string|max:120')]
    public string $name = '';

    #[Validate('required|string|email|max:255|unique:users,email')]
    public string $email = '';

    #[Validate('required|string|min:8|confirmed')]
    public string $password = '';

    #[Validate('required|string')]
    public string $password_confirmation = '';

    /**
     * Opt-in for the 30-day no-CC Pro trial. Checked by default —
     * every signup should get the full-feature experience unless they
     * explicitly say no. Nothing bills at the end, ever, so there is
     * no dark-pattern angle here (there IS no card).
     */
    public bool $start_trial = true;

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

        if ($this->start_trial) {
            // Ignore the return — StartProTrial::for is idempotent
            // and a fresh user is always eligible. No-op on failure.
            StartProTrial::for($user);
        }

        event(new Registered($user));

        Auth::login($user);
        Session::regenerate();

        $this->redirect('/my-calendar', navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.shooter-register')
            ->layout('components.layouts.public', ['title' => 'Create a shooter account']);
    }
}
