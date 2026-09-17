<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EventResource;
use App\Models\Event;
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
            ->with(['hostOrganisation.parent', 'venue', 'disciplines', 'flags', 'banner'])
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
        $user->savedEvents()->syncWithoutDetaching([$data['event_id']]);

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
