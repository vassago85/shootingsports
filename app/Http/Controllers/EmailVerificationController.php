<?php

namespace App\Http\Controllers;

use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Email verification endpoints. Wired to three named routes:
 *
 *   verification.notice — GET /email/verify, the "check your inbox"
 *                         page shown by the `verified` middleware and
 *                         after registration.
 *   verification.verify — GET /email/verify/{id}/{hash}, the signed
 *                         link inside the email itself.
 *   verification.send   — POST /email/verification-notification, the
 *                         "resend link" button on the notice page.
 *
 * Post-verification we land the user on `/suppliers/onboard` so a
 * supplier can immediately fill in their listing form. Non-suppliers
 * (shooters, MDs whose accounts pre-dated verification) simply drop
 * back to their default post-login path.
 */
class EmailVerificationController extends Controller
{
    public function notice(Request $request): RedirectResponse|View
    {
        $user = $request->user();

        if ($user !== null && $user->hasVerifiedEmail()) {
            return redirect()->to($this->postVerificationPath($request));
        }

        return view('auth.verify-email');
    }

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->to($this->postVerificationPath($request).'?verified=1');
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return redirect()->to($this->postVerificationPath($request).'?verified=1');
    }

    public function resend(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->to($this->postVerificationPath($request));
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }

    /**
     * Route to send the user to after they have verified. Preserves
     * any `intended` URL captured by the auth middleware, then falls
     * back to /suppliers/onboard for anyone in mid-supplier-signup,
     * and finally to the user's default role landing.
     */
    private function postVerificationPath(Request $request): string
    {
        $intended = $request->session()->pull('url.intended');

        if (is_string($intended) && $intended !== '') {
            return $intended;
        }

        // A user with no listings yet who came in through /suppliers/register
        // needs to complete their listing — send them there. Users who
        // already own at least one listing (returning suppliers) are
        // treated as "done" and go to their default landing.
        $user = $request->user();

        if ($user !== null && ! $user->isSupplier() && $request->session()->has('supplier.pending_business_name')) {
            return route('suppliers.onboard');
        }

        return $user?->defaultRedirectPath() ?? route('home');
    }
}
