<?php

namespace App\Http\Responses;

use Filament\Auth\Http\Responses\Contracts\LogoutResponse as LogoutResponseContract;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Overrides Filament's default post-logout redirect for both panels
 * (/admin and /desk).
 *
 * Filament's stock behaviour sends the user back to the panel login
 * screen — which reads as "the site kicked me out into a dead end".
 * Sending them to the public home page instead keeps them on the
 * marketing surface where they can browse the calendar or sign back
 * in from the header. Same behaviour as every consumer SaaS.
 *
 * Binding lives in AppServiceProvider::register() so it wins over
 * Filament's default binding for BOTH panels without needing to
 * duplicate it in each PanelProvider.
 */
class LogoutResponse implements LogoutResponseContract
{
    public function toResponse($request): RedirectResponse|Response
    {
        return redirect('/');
    }
}
