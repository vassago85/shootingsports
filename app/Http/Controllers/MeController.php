<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\DeskCapabilities;
use Illuminate\View\View;

/**
 * The shooter's home on the public site. Every account is a shooter
 * first; the Manage block only appears when the user has stacked a
 * club, shop, range, or media capability on top. Heavy desk work
 * (members, invoices, magazine, billing) stays behind the website
 * links on each capability card.
 */
class MeController extends Controller
{
    public function show(): View
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);
        $user->ensureCalendarSlug();

        $capabilityCards = DeskCapabilities::for($user);

        $logLimit = $user->limit('attended_events_slots');
        $followLimit = $user->limit('follows');

        // Next two saved matches shown inline on the My calendar card so
        // the page is useful at a glance instead of a wall of four links.
        // Eager-loads venue so we can show the location without a per-row
        // query — Expensive-changes rule.
        $nextSavedEvents = $user->savedEvents()
            ->with('venue:id,name,town')
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->limit(2)
            ->get();

        return view('public.me.index', [
            'user' => $user,
            'planLabel' => $user->subscriptionStatusLabel(),
            'planIsPro' => $user->isPro(),
            'onTrial' => $user->isOnTrial(),
            'capabilityCards' => $capabilityCards,
            'hasDeskAccess' => $user->hasDeskAccess(),
            'logCount' => $user->attendedEvents()->count(),
            'logLimit' => $logLimit,
            'followCount' => $user->follows()->count(),
            'followLimit' => $followLimit,
            'savedCount' => $user->savedEvents()->count(),
            'nextSavedEvents' => $nextSavedEvents,
        ]);
    }
}
