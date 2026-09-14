<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Support\EventSpecRows;
use App\Support\JsonLd;
use Illuminate\View\View;

class EventController extends Controller
{
    public function show(Event $event): View
    {
        abort_if($event->status === EventStatus::Draft, 404);

        $event->load(['hostOrganisation', 'venue', 'disciplines', 'flags', 'banner']);

        return view('public.matches.show', [
            'event' => $event,
            'specs' => EventSpecRows::for($event),
            'jsonLd' => JsonLd::event($event),
        ]);
    }
}
