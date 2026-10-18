<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin panel auth gate with a soft landing for the wrong panel.
 *
 * Filament's stock Authenticate middleware calls abort(403) when
 * `canAccessPanel()` returns false. That is correct for an unknown
 * user, but harsh for an authenticated desk user who typed `/admin`
 * out of habit — they end up staring at a bare 403 page. This
 * middleware keeps the hard `is_staff` gate but redirects wrong-panel
 * requests to the panel the user *can* reach, with a short status
 * flash explaining why.
 *
 * - Guests: fall through to Filament's unauthenticated handler.
 * - Signed-in staff: allowed.
 * - Signed-in desk-capable user: 302 → `/desk` + flash.
 * - Everyone else: 302 → `defaultRedirectPath()` (usually `/my-calendar`).
 */
class AuthenticateAdminPanel extends FilamentAuthenticate
{
    public function handle($request, Closure $next, ...$guards): Response
    {
        $guard = Filament::auth();

        if (! $guard->check()) {
            // Delegate to Filament's unauthenticated handler so the
            // intended URL + login redirect behaviour stays identical.
            return parent::handle($request, $next, ...$guards);
        }

        $user = $guard->user();
        $panel = Filament::getCurrentOrDefaultPanel();

        if ($panel !== null && $user instanceof User && ! $user->canAccessPanel($panel)) {
            return $this->redirectWrongPanel($user, $request);
        }

        return parent::handle($request, $next, ...$guards);
    }

    private function redirectWrongPanel(User $user, Request $request): Response
    {
        // Phase C opens /desk to every signed-in user (no-access
        // landing for shooters), so canAccessPanel('desk') is always
        // true. Only bounce to /desk when they actually manage
        // something — otherwise send them to their default home.
        if ($user->deskHomeMode() !== 'none') {
            return redirect('/desk')
                ->with('status', 'The admin area is staff only. You have been sent to your desk.');
        }

        return redirect($user->defaultRedirectPath())
            ->with('status', 'The admin area is staff only.');
    }
}
