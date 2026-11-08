<?php

namespace App\Http\Controllers;

use App\Enums\ProfileVisibility;
use App\Models\Event;
use App\Models\User;
use App\Queries\PublicEventQuery;
use App\Support\Rankings;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShooterCalendarController extends Controller
{
    public function mine(): View
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);
        $user->ensureCalendarSlug();

        if (blank($user->calendar_token)) {
            $user->rotateCalendarToken();
        }

        $savedIds = $user->savedEvents()->pluck('events.id')->all();

        $upcoming = (new PublicEventQuery(eventIds: $savedIds))->get();

        // History window is a personalisation depth cap — the data is
        // still in the DB, we just refuse to render it beyond the free
        // horizon. Pro (unlimited) returns null from limit() and skips
        // the floor entirely.
        $historyMonths = $user->limit('history_months');

        [$past, $historyClipped] = $this->pastSavedEvents($savedIds, $historyMonths);

        return view('public.shooters.mine', [
            'user' => $user,
            'events' => $upcoming,
            'past' => $past,
            'historyClipped' => $historyClipped,
            'savedCount' => count($savedIds),
            'savedLimit' => $user->limit('saved_events'),
            // Any status is listed so a shooter can see pending
            // applications and ex-memberships, not only Active rows.
            'clubMemberships' => $user->clubMemberships()
                ->with('organisation')
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public function show(string $shooter, Rankings $rankings): View
    {
        $user = User::query()->where('calendar_slug', $shooter)->firstOrFail();

        $viewer = auth()->user();

        abort_unless($user->profileVisibleTo($viewer instanceof User ? $viewer : null), 404);

        $events = (new PublicEventQuery(
            eventIds: $user->savedEvents()->pluck('events.id')->all(),
        ))->get();

        return view('public.shooters.show', [
            'user' => $user,
            'events' => $events,
            'profile' => $rankings->profile($user),
        ]);
    }

    public function updateVisibility(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $visibility = ProfileVisibility::tryFrom((string) $request->input('profile_visibility'));

        abort_unless($visibility instanceof ProfileVisibility, 422);

        $user->forceFill(['profile_visibility' => $visibility])->save();

        return redirect()
            ->route('my-calendar')
            ->with('status', $visibility === ProfileVisibility::Public
                ? 'Your shooter page is now public.'
                : 'Your shooter page is now private.');
    }

    public function resetCalendarLink(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $user->rotateCalendarToken();

        return redirect()
            ->route('my-calendar')
            ->with('status', 'Your calendar link has been reset. The previous link no longer works.');
    }

    /**
     * Past saved matches, most-recent first. When $historyMonths is a
     * positive int the query is floored at that horizon and the second
     * return element is true iff at least one saved match sits beyond
     * the horizon — the template uses that flag to render the single
     * "Earlier matches are in your Pro history" cut-off row.
     *
     * @param  list<int>  $savedIds
     * @return array{0: Collection<int, Event>, 1: bool}
     */
    private function pastSavedEvents(array $savedIds, ?int $historyMonths): array
    {
        if ($savedIds === []) {
            return [new Collection, false];
        }

        $base = Event::query()
            ->with(['hostOrganisation.parent', 'venue', 'disciplines', 'flags', 'banner'])
            ->whereIn('id', $savedIds)
            ->where('starts_at', '<', now())
            ->orderByDesc('starts_at');

        if ($historyMonths === null) {
            return [$base->get(), false];
        }

        $floor = now()->subMonths($historyMonths);

        $visible = (clone $base)->where('starts_at', '>=', $floor)->get();

        $clipped = Event::query()
            ->whereIn('id', $savedIds)
            ->where('starts_at', '<', $floor)
            ->exists();

        return [$visible, $clipped];
    }
}
