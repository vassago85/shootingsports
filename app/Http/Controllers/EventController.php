<?php

namespace App\Http\Controllers;

use App\Enums\EventKind;
use App\Enums\EventStatus;
use App\Models\Article;
use App\Models\Event;
use App\Queries\PublicEventQuery;
use App\Support\EventSpecRows;
use App\Support\JsonLd;
use App\Support\Seo;
use Illuminate\View\View;

class EventController extends Controller
{
    public function show(Event $event): View
    {
        abort_if($event->status === EventStatus::Draft, 404);

        $event->load([
            'hostOrganisation.parent',
            'venue',
            'venues',
            'disciplines.parent',
            'disciplines.divisionLinks',
            'flags',
            'banner',
            'partners' => fn ($query) => $query->published()->listed(),
        ]);

        $disciplineIds = $event->disciplines->pluck('id');
        $articles = Article::query()
            ->published()
            ->where(function ($query) use ($event, $disciplineIds): void {
                $query->whereJsonContains('event_kinds', $event->kind?->value ?? EventKind::Competition->value);

                if ($disciplineIds->isNotEmpty()) {
                    $query->orWhereHas('disciplines', fn ($inner) => $inner->whereIn('disciplines.id', $disciplineIds));
                }
            })
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        // Breadcrumb: Home › Calendar › (Province)? › Match title. The
        // province rung uses the venue's province so a match in a
        // random venue-less town doesn't invent geography — falls
        // through to the host org's province, then omits entirely.
        $province = $event->venue?->province ?? $event->hostOrganisation?->province;

        $crumbs = [
            ['name' => 'Home', 'url' => route('home')],
            ['name' => 'Calendar', 'url' => route('calendar')],
        ];

        if ($province !== null) {
            $crumbs[] = [
                'name' => $province->getLabel(),
                'url' => route('calendar', ['province' => $province->urlSlug()]),
            ];
        }

        $crumbs[] = [
            'name' => $event->title,
            'url' => $event->publicUrl(),
        ];

        // Related upcoming matches in any of this event's disciplines
        // — helps a shooter who missed the entry cut find the next
        // one. Reuses the same public query so filters and ordering
        // stay consistent with the calendar.
        $relatedEvents = collect();

        if ($disciplineIds->isNotEmpty()) {
            $relatedEvents = (new PublicEventQuery(
                disciplineIds: $disciplineIds->all(),
                limit: 6,
            ))->get()
                ->reject(fn (Event $candidate): bool => $candidate->id === $event->id)
                ->take(4)
                ->values();
        }

        // Past match surfacing: when the match being viewed is already
        // over (Completed or Postponed, or its start time is in the
        // past), load the host club's next 4 upcoming matches so the
        // page has a path forward instead of ending at a dead date.
        $isPast = $event->status === EventStatus::Completed
            || $event->status === EventStatus::Postponed
            || ($event->starts_at !== null && $event->starts_at->isPast());

        $upcomingByHost = collect();

        if ($isPast && $event->host_organisation_id !== null) {
            $upcomingByHost = (new PublicEventQuery(
                organisationId: $event->host_organisation_id,
                limit: 4,
            ))->get()
                ->reject(fn (Event $candidate): bool => $candidate->id === $event->id)
                ->values();
        }

        return view('public.matches.show', [
            'event' => $event,
            'articles' => $articles,
            'relatedEvents' => $relatedEvents,
            'upcomingByHost' => $upcomingByHost,
            'specs' => EventSpecRows::for($event),
            'seo' => Seo::forEvent($event),
            'jsonLd' => [
                JsonLd::event($event),
                JsonLd::breadcrumbs($crumbs),
            ],
        ]);
    }
}
