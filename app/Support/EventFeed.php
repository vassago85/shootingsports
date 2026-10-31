<?php

namespace App\Support;

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
            $when = $event->starts_at?->timezone((string) config('app.timezone'))->format('j M Y') ?? 'Date to be confirmed';
            $lines[] = collect([
                $when,
                $event->title,
                $event->hostOrganisation?->name,
                collect([$event->venue?->name, $event->venue?->town, $event->venue?->province?->getLabel()])->filter()->implode(', '),
                $event->publicUrl(),
            ])->filter()->implode(' — ');
        }

        return implode("\n", $lines)."\n";
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
