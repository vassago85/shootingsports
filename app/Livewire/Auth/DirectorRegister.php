<?php

namespace App\Livewire\Auth;

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Models\Enquiry;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Match director signup. Same shape as ShooterRegister but the new
 * account is flagged `is_match_director = true` on creation, which
 * unlocks /desk access.
 *
 * When the applicant tells us which host/club they intend to publish
 * for, we record a lightweight md_signup enquiry so staff can pair
 * the account with an existing organisation (or flag a shady one).
 * The account is still created either way — the hint is optional.
 */
class DirectorRegister extends Component
{
    #[Validate('required|string|max:120')]
    public string $name = '';

    #[Validate('required|string|email|max:255|unique:users,email')]
    public string $email = '';

    #[Validate('required|string|min:8|confirmed')]
    public string $password = '';

    #[Validate('required|string')]
    public string $password_confirmation = '';

    /** Free-text: "which host / club will you be publishing events for?" */
    #[Validate('nullable|string|max:500')]
    public string $host_hint = '';

    public function register(): void
    {
        $this->validate();

        $user = User::create([
            'name' => trim($this->name),
            'email' => strtolower(trim($this->email)),
            'password' => Hash::make($this->password),
            'is_staff' => false,
            'is_match_director' => true,
        ]);

        event(new Registered($user));

        if (filled($this->host_hint)) {
            Enquiry::create([
                'type' => EnquiryType::MdSignup,
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'subject' => 'New match director: '.$user->name,
                'body' => trim($this->host_hint),
                'context' => ['host_hint' => trim($this->host_hint)],
                'status' => EnquiryStatus::New,
                'ip_address' => request()->ip(),
                'user_agent' => (string) request()->userAgent(),
            ]);
        }

        Auth::login($user);
        Session::regenerate();

        $this->redirect('/desk', navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.director-register')
            ->layout('components.layouts.public', ['title' => 'Register as a match director']);
    }
}
