<?php

namespace App\Http\Controllers;

use App\Support\AnswerPages;
use App\Support\EventFeed;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Public JSON feed of upcoming events.
 *
 * `GET /events.json` returns `{ generated_at, description, events[] }`.
 * Each event uses the same fields as the Event JSON-LD on its page
 * (`name`, `startDate`, `endDate`, `eventStatus`, `eventAttendanceMode`,
 * `location`, `organizer`, `url`, `offers` when a fee is known) plus
 * `last_updated`. The body is cached and regenerated when
 * `answers:refresh` stamps a new lastmod.
 */
class EventsJsonController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $payload = Cache::remember(
            'feeds.events-json.'.AnswerPages::lastmod(),
            now()->addHour(),
            fn (): array => EventFeed::json(),
        );

        return response()
            ->json($payload)
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
