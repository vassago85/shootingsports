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
 *                         link inside the email itself. An expired
 *                         signature is caught in bootstrap/app.php and
 *                         sent back here so the user can request another.
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
            $path = $this->postVerificationPath($request);
            $separator = str_contains($path, '?') ? '&' : '?';

            return redirect()->to($path.$separator.'verified=1');
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        $path = $this->postVerificationPath($request);
        $separator = str_contains($path, '?') ? '&' : '?';

        return redirect()->to($path.$separator.'verified=1');
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
     * Route to send the user to after they have verified.
     *
     * A half-finished supplier listing wins over a random intended URL,
     * so confirming the email on another device still opens the form.
     * An intended supplier or claim URL is kept, because that is the
     * page they were already on. Match directors go to the match form
     * once the listing step is done.
     */
    private function postVerificationPath(Request $request): string
    {
        $intended = $request->session()->pull('url.intended');
        $continuation = $this->supplierContinuation($intended);
        $user = $request->user();

        if ($continuation !== null) {
            return $continuation;
        }

        if ($user !== null && $user->needsSupplierOnboarding()) {
            return route('suppliers.onboard');
        }

        $safeIntended = $this->sameOrigin($intended);

        if ($safeIntended !== null) {
            return $safeIntended;
        }

        if ($user !== null && ($request->session()->has('md.pending_host') || $user->isMdPending())) {
            return route('matches.submit');
        }

        return $user?->defaultRedirectPath() ?? route('home');
    }

    /**
     * Supplier onboarding and claim URLs are the pages a person was
     * in the middle of. Other intended URLs must not skip the listing.
     */
    private function supplierContinuation(mixed $intended): ?string
    {
        $url = $this->sameOrigin($intended);

        if ($url === null) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path)) {
            return null;
        }

        if ($path === '/suppliers/onboard' || str_starts_with($path, '/suppliers/onboard/')) {
            return $url;
        }

        if (preg_match('#^/supplier/[^/]+/claim$#', $path) === 1) {
            return $url;
        }

        return null;
    }

    private function sameOrigin(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($appHost) || ! is_string($host) || strcasecmp($appHost, $host) !== 0) {
            return null;
        }

        return $url;
    }
}
