<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Queries\PublicEventQuery;
use Illuminate\View\View;

class ShooterCalendarController extends Controller
{
    public function mine(): View
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);
        $user->ensureCalendarSlug();

        $events = (new PublicEventQuery(
            eventIds: $user->savedEvents()->pluck('events.id')->all(),
        ))->get();

        return view('public.shooters.mine', [
            'user' => $user,
            'events' => $events,
        ]);
    }

    public function show(string $shooter): View
    {
        $user = User::query()->where('calendar_slug', $shooter)->firstOrFail();

        $events = (new PublicEventQuery(
            eventIds: $user->savedEvents()->pluck('events.id')->all(),
        ))->get();

        return view('public.shooters.show', [
            'user' => $user,
            'events' => $events,
        ]);
    }
}
