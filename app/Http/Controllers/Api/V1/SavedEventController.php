<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\PlanLimitExceeded;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EventResource;
use App\Models\Event;
use App\Models\SavedEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Mobile equivalent of Livewire SaveToCalendar. Same underlying
 * pivot (`saved_events`) so a match saved on web appears on the
 * device and vice versa.
 */
class SavedEventController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $events = $user
            ->savedEvents()
            ->with(['hostOrganisation.parent', 'venue', 'disciplines.parent', 'flags', 'banner'])
            ->orderBy('starts_at')
            ->get();

        return EventResource::collection($events);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_id' => ['required', 'integer', 'exists:events,id'],
        ]);

        $user = $request->user();
        $user->ensureCalendarSlug();

        $alreadySaved = SavedEvent::query()
            ->where('user_id', $user->id)
            ->where('event_id', $data['event_id'])
            ->exists();

        if (! $alreadySaved) {
            try {
                SavedEvent::create([
                    'user_id' => $user->id,
                    'event_id' => $data['event_id'],
                ]);
            } catch (PlanLimitExceeded $e) {
                return response()->json([
                    'message' => 'Saved match limit reached on your current plan.',
                    'code' => $e->trigger,
                    'trigger' => $e->trigger,
                    'limit' => $e->user->limit($e->limitKey),
                    'current' => $e->current,
                ], 403);
            }
        }

        return response()->json([
            'event_id' => (int) $data['event_id'],
            'saved' => true,
        ], 201);
    }

    public function destroy(Request $request, int $eventId): JsonResponse
    {
        // Manual lookup rather than route-model binding: `{event}` is
        // bound app-wide to slug lookup in AppServiceProvider, and the
        // mobile client sends numeric ids on the pivot.
        $event = Event::query()->findOrFail($eventId);

        $request->user()->savedEvents()->detach($event->id);

        return response()->json([
            'event_id' => $event->id,
            'saved' => false,
        ]);
    }
}
