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
 * Match director signup with staff-review gating.
 *
 * The applicant creates a normal shooter account (is_match_director
 * = false). We stamp md_requested_at so the account shows up in the
 * staff dashboard's pending queue, and we always record an MdSignup
 * enquiry — the host_hint is required because it is the reviewer's
 * only signal that this is a real person from a real club and not
 * a random pollution attempt.
 *
 * The applicant is auto-logged-in and lands on /my-calendar with a
 * "pending review" banner. /desk stays 403 until a staff member fires
 * the approve action from UserResource (which flips is_match_director
 * to true, sets md_approved_at, and emails the applicant).
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

    /**
     * The applicant tells us which host/club/series they intend to
     * publish events for. Required in the review-gated flow because
     * staff need something to check against — an unnamed request is
     * indistinguishable from a scraper.
     */
    #[Validate('required|string|min:5|max:500')]
    public string $host_hint = '';

    public function register(): void
    {
        $this->validate();

        $user = User::create([
            'name' => trim($this->name),
            'email' => strtolower(trim($this->email)),
            'password' => Hash::make($this->password),
            'is_staff' => false,
            'is_match_director' => false,
            'md_requested_at' => now(),
        ]);

        event(new Registered($user));

        // MdSignup enquiry is the review payload — subject prefixed
        // with "MD request" so the staff inbox reads at a glance.
        Enquiry::create([
            'type' => EnquiryType::MdSignup,
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'subject' => 'MD request: '.$user->name,
            'body' => trim($this->host_hint),
            'context' => ['host_hint' => trim($this->host_hint)],
            'status' => EnquiryStatus::New,
            'ip_address' => request()->ip(),
            'user_agent' => (string) request()->userAgent(),
        ]);

        Auth::login($user);
        Session::regenerate();

        session()->flash('status', 'Thanks — your match director request is in. We usually review within one working day, and you will get an email as soon as it is approved. In the meantime, your shooter calendar works exactly as normal.');

        $this->redirect('/my-calendar', navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.director-register')
            ->layout('components.layouts.public', ['title' => 'Register as a match director']);
    }
}
