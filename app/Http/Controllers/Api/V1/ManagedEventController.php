<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EventResource;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Light manage endpoints on top of the shooter API. The mobile app
 * owns a short list of actions that a club admin reasonably wants to
 * take from their phone (change status, mark postponed, cancel);
 * everything heavier (members, invoices, event wizard, publication
 * flow) stays on the desk website.
 */
class ManagedEventController extends Controller
{
    /**
     * Statuses a desk user may apply from the app. Draft stays
     * off-limits because publishing is a one-way move and the event
     * wizard is the right place for it; Completed is reached
     * automatically by the end-of-day sweep, not a manual choice.
     */
    private const array APP_SETTABLE_STATUSES = [
        EventStatus::Planned,
        EventStatus::Confirmed,
        EventStatus::EntriesOpen,
        EventStatus::Full,
        EventStatus::Postponed,
        EventStatus::Cancelled,
    ];

    /**
     * Upcoming events hosted by any club the current user manages.
     * Mirrors the desk home's "upcoming events" list so the mobile
     * Club card has the same source of truth.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $orgIds = $user->managedOrganisations()->pluck('organisations.id');

        $events = Event::query()
            ->whereIn('host_organisation_id', $orgIds)
            ->where('starts_at', '>=', now()->startOfDay())
            ->orderBy('starts_at')
            ->with(['hostOrganisation.parent', 'venue', 'disciplines', 'flags', 'banner'])
            ->limit(100)
            ->get();

        return EventResource::collection($events);
    }

    /**
     * Update the lifecycle status of a single managed event. Deliberately
     * narrow — the app does not get to rename, re-date, re-venue, or
     * republish events. Everything else lives on the desk website.
     */
    public function updateStatus(Request $request, int $eventId): JsonResponse
    {
        $event = Event::query()->findOrFail($eventId);
        $user = $request->user();

        if ($user->cannot('update', $event)) {
            abort(403);
        }

        $data = $request->validate([
            'status' => ['required', 'string'],
        ]);

        $status = EventStatus::tryFrom($data['status']);

        if ($status === null || ! in_array($status, self::APP_SETTABLE_STATUSES, true)) {
            abort(422, 'Status is not one the app may set from here.');
        }

        $event->forceFill(['status' => $status->value])->save();

        return response()->json([
            'event_id' => $event->id,
            'status' => $status->value,
            'status_label' => $status->getLabel(),
        ]);
    }
}
