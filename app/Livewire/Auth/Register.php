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
 * Unified public signup — one form, multiple roles.
 *
 * Every account starts as a shooter (save matches, follow clubs, keep
 * an attendance log). Two optional tickboxes stack on top:
 *
 *   - "Publish matches for a club, range or series" (match director)
 *       Requires a host_hint so staff have something to review. The
 *       user is created is_match_director=false with md_requested_at
 *       set, and an MdSignup enquiry lands in the staff queue. The
 *       hint is stashed in the session and, after email confirmation,
 *       they go to /matches/submit. The match stays a draft until
 *       staff approve it.
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
 *   md ticked       → /email/verify, then /matches/submit
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

    public function mount(): void
    {
        if (request()->boolean('director')) {
            $this->wants_md = true;
        }
    }

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

        if ($this->wants_md) {
            // Shown on /matches/submit so the club note they typed is
            // still in front of them after the email confirmation hop.
            Session::put('md.pending_host', trim($this->host_hint));
        }

        // The Pro trial is intentionally not auto-started at signup —
        // users land on Free and can opt in from /upgrade whenever they
        // like. Keeps the signup form short and stops us from burning a
        // one-shot 30-day trial on someone who never comes back.

        event(new Registered($user));

        Auth::login($user);
        Session::regenerate();

        if ($this->wants_supplier || $this->wants_md) {
            // Both follow-on forms are verified-gated. Supplier
            // onboarding wins when both boxes are ticked; the match
            // form is the next stop once that listing exists.
            if ($this->wants_md && ! $this->wants_supplier) {
                session()->flash('status', 'Confirm your email, then submit your match. It stays off the calendar until we approve it.');
            }

            $this->redirect(route('verification.notice'), navigate: false);

            return;
        }

        $this->redirect('/my-calendar', navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.register')
            ->layout('components.layouts.public', ['title' => 'Create your account']);
    }
}
