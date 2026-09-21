<?php

namespace App\Livewire\Auth;

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Models\Enquiry;
use App\Models\User;
use App\Services\StartProTrial;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Unified public signup — one form, multiple roles.
 *
 * Every account starts as a shooter (save matches, follow clubs, keep
 * an attendance log). Two optional tickboxes stack on top:
 *
 *   - "Publish matches for a club, range or series" (match director)
 *       Requires a host_hint so staff have something to review. The
 *       user is created is_match_director=false with md_requested_at
 *       set, and an MdSignup enquiry lands in the staff queue.
 *
 *   - "List my business in the industry directory" (supplier)
 *       Requires a business_name. The name is stashed in the session
 *       so /suppliers/onboard can pre-fill the listing form. A signed
 *       verification email is dispatched by the Registered listener,
 *       and the /suppliers/onboard route hard-gates on `verified`.
 *
 * Both tickboxes can be ticked on the same account — one login, three
 * roles. The post-signup redirect is layered:
 *
 *   supplier ticked → /email/verify (must confirm before onboarding)
 *   md ticked       → /my-calendar with "pending review" flash
 *   neither         → /my-calendar
 */
class Register extends Component
{
    #[Validate('required|string|max:120')]
    public string $name = '';

    #[Validate('required|string|email|max:255|unique:users,email')]
    public string $email = '';

    #[Validate('required|string|min:8|confirmed')]
    public string $password = '';

    #[Validate('required|string')]
    public string $password_confirmation = '';

    public bool $wants_md = false;

    /**
     * Free-text description of the club / series / range the applicant
     * intends to publish matches for. Only required when wants_md is
     * ticked — validated in register(), not via attribute rule.
     */
    public string $host_hint = '';

    public bool $wants_supplier = false;

    /**
     * Public-facing name of the supplier's business (e.g. "Delmas Gun
     * Shop"). Only required when wants_supplier is ticked. Stashed in
     * the session for /suppliers/onboard to pre-fill.
     */
    public string $business_name = '';

    public bool $start_trial = true;

    public function register(): void
    {
        $rules = [
            'name' => 'required|string|max:120',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
        ];

        if ($this->wants_md) {
            $rules['host_hint'] = 'required|string|min:5|max:500';
        }

        if ($this->wants_supplier) {
            $rules['business_name'] = 'required|string|max:160';
        }

        $this->validate($rules);

        $user = User::create([
            'name' => trim($this->name),
            'email' => strtolower(trim($this->email)),
            'password' => Hash::make($this->password),
            'is_staff' => false,
            'is_match_director' => false,
            'md_requested_at' => $this->wants_md ? now() : null,
        ]);

        if ($this->wants_md) {
            // MdSignup enquiry is the review payload. Subject prefix
            // "MD request" is what the staff inbox groups on.
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
        }

        if ($this->wants_supplier) {
            // Picked up by CreateListing::mount() so the applicant does
            // not retype the business name. Cleared once consumed.
            Session::put('supplier.pending_business_name', trim($this->business_name));
        }

        if ($this->start_trial) {
            // Idempotent + no-ops if ineligible. Every signup should
            // land on Pro trial unless they explicitly opted out.
            StartProTrial::for($user);
        }

        event(new Registered($user));

        Auth::login($user);
        Session::regenerate();

        if ($this->wants_supplier) {
            // Supplier onboarding is verified-gated so we must send
            // them via the notice first. Verification link redirects
            // to /suppliers/onboard afterwards (see EmailVerificationController).
            $this->redirect(route('verification.notice'), navigate: false);

            return;
        }

        if ($this->wants_md) {
            session()->flash('status', 'Thanks — your match director request is in. We usually review within one working day, and you will get an email as soon as it is approved. In the meantime, your shooter account works exactly as normal.');
        }

        $this->redirect('/my-calendar', navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.register')
            ->layout('components.layouts.public', ['title' => 'Create your account']);
    }
}
