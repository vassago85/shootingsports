<?php

namespace App\Support;

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Public machine-readable event feeds.
 *
 * `/events.json` lists upcoming events with the same fields as the
 * Event JSON-LD graph, plus `last_updated`. `/llms-full.txt` is the
 * next 60 days as plain sentences. Both are cached; `answers:refresh`
 * changes the cache key by stamping {@see AnswerPages::lastmod()}.
 */
final class EventFeed
{
    /**
     * @return array<string, mixed>
     */
    public static function json(): array
    {
        $events = self::upcoming(null);

        return [
            'generated_at' => now()->toAtomString(),
            'description' => 'Upcoming shooting events in South Africa. Fields match the Event structured data on each event page, plus last_updated.',
            'events' => $events->map(function (Event $event): array {
                $payload = JsonLd::event($event);
                unset($payload['@context']);
                $payload['last_updated'] = $event->updated_at?->toAtomString();

                return $payload;
            })->values()->all(),
        ];
    }

    public static function plainText(int $days = 60): string
    {
        $from = now()->timezone((string) config('app.timezone'))->startOfDay();
        $to = $from->copy()->addDays($days)->endOfDay();
        $events = self::upcoming($to);

        $lines = [
            'Shooting Sports is the national register of shooting events, clubs, and ranges in South Africa.',
            '',
            'Events from '.$from->format('j M Y').' to '.$to->format('j M Y').'.',
            '',
        ];

        if ($events->isEmpty()) {
            $lines[] = 'No events are listed in this window.';

            return implode("\n", $lines)."\n";
        }

        foreach ($events as $event) {
            $lines[] = self::plainLine($event);
        }

        return implode("\n", $lines)."\n";
    }

    private static function plainLine(Event $event): string
    {
        $timezone = (string) config('app.timezone');
        $start = $event->starts_at?->timezone($timezone);
        $end = $event->ends_at?->timezone($timezone);
        $when = 'Date to be confirmed';

        if ($start instanceof Carbon) {
            $when = $start->format('j M Y');

            if ($end instanceof Carbon && ! $end->isSameDay($start)) {
                $when .= '–'.$end->format('j M Y');
            }

            $today = now()->timezone($timezone)->startOfDay();

            if ($end instanceof Carbon && $start->lt($today) && $end->endOfDay()->gte($today)) {
                $when .= ' (ongoing)';
            }
        }

        if ($event->status === EventStatus::Planned) {
            $when .= ' (provisional)';
        }

        $facts = PublicEventFacts::for($event);
        $checked = $event->last_verified_at?->timezone($timezone)->format('j M Y');

        return collect([
            $when,
            $event->title,
            $event->hostOrganisation?->name,
            $facts->venueLine(),
            $facts->provinceLabel(),
            $facts->priceSentence(),
            $event->source?->getLabel(),
            $checked !== null ? 'checked '.$checked : null,
            $event->publicUrl(),
        ])->filter()->unique()->implode(' — ');
    }

    /**
     * @return Collection<int, Event>
     */
    private static function upcoming(?Carbon $until): Collection
    {
        return Event::query()
            ->upcoming()
            ->with(['venue', 'hostOrganisation', 'disciplines', 'banner'])
            ->when($until, fn ($query) => $query->where('starts_at', '<=', $until))
            ->orderBy('starts_at')
            ->limit(500)
            ->get();
    }
}
