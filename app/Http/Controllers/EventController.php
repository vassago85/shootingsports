<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Support\EventSpecRows;
use App\Support\JsonLd;
use App\Support\Seo;
use Illuminate\View\View;

class EventController extends Controller
{
    public function show(Event $event): View
    {
        abort_if($event->status === EventStatus::Draft, 404);

        $event->load(['hostOrganisation.parent', 'venue', 'venues', 'disciplines.divisionLinks', 'flags', 'banner']);

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

        return view('public.matches.show', [
            'event' => $event,
            'specs' => EventSpecRows::for($event),
            'seo' => Seo::forEvent($event),
            'jsonLd' => [
                JsonLd::event($event),
                JsonLd::breadcrumbs($crumbs),
            ],
        ]);
    }
}
