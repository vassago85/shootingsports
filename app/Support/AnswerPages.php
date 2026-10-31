<?php

namespace App\Support;

use App\Enums\Province;
use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Server-rendered answer pages: this weekend, nationally and per
 * province. Discipline hubs live at /disciplines/{slug}. The daily
 * `answers:refresh` command stamps
 * {@see self::STAMP} so sitemap lastmod and the plain-text feed move
 * together.
 */
final class AnswerPages
{
    public const STAMP = 'seo.landings.generated_at';

    public static function stamp(): void
    {
        Cache::forever(self::STAMP, now()->toAtomString());
    }

    public static function lastmod(): string
    {
        $stamp = Cache::get(self::STAMP);

        if (is_string($stamp) && $stamp !== '') {
            return Carbon::parse($stamp)->toAtomString();
        }

        return now()->startOfDay()->toAtomString();
    }

    /**
     * @return array<string, mixed>
     */
    public static function weekend(?Province $province, bool $next): array
    {
        [$from, $to] = $next ? SaturdayWeekend::next() : SaturdayWeekend::this();
        $range = SaturdayWeekend::label($from, $to);
        $place = $province?->getLabel() ?? 'South Africa';
        $when = $next ? 'next weekend' : 'this weekend';

        $events = self::between($from, $to, $province);
        $count = $events->count();
        $noun = $count === 1 ? '1 event' : $count.' events';
        $title = 'Shooting matches in '.$place.' '.$when.' ('.$range.'): '.$noun;

        $paragraphs = $count === 0
            ? ['No shooting matches are listed in '.$place.' for '.$range.'.']
            : array_merge(
                [$count.' shooting '.str('event')->plural($count).' '.$when.' ('.$range.') in '.$place.'.'],
                self::sentences($events),
            );

        $later = $count === 0
            ? self::between($to->copy()->addSecond(), now()->copy()->addMonths(6), $province)->take(5)
            : collect();

        if ($later->isNotEmpty()) {
            $paragraphs[] = 'Later events are linked below.';
        }

        return self::payload(
            title: $title,
            description: $paragraphs[0],
            canonical: self::weekendUrl($province, $next),
            robots: null,
            events: $events,
            later: $later,
            paragraphs: $paragraphs,
            provinceLabel: $province?->getLabel(),
        );
    }

    /**
     * @return Collection<int, array{loc: string, lastmod: string}>
     */
    public static function sitemapUrls(): Collection
    {
        $lastmod = self::lastmod();
        $urls = collect([
            ['loc' => route('answers.this-weekend'), 'lastmod' => $lastmod],
        ]);

        foreach (Province::cases() as $province) {
            $urls->push(
                ['loc' => route('answers.province-this-weekend', $province->urlSlug()), 'lastmod' => $lastmod],
                ['loc' => route('answers.province-next-weekend', $province->urlSlug()), 'lastmod' => $lastmod],
            );
        }

        return $urls;
    }

    /**
     * @return list<string>
     */
    public static function publicUrls(): array
    {
        return self::sitemapUrls()->pluck('loc')->all();
    }

    /**
     * @return Collection<int, Event>
     */
    private static function between(Carbon $from, Carbon $to, ?Province $province): Collection
    {
        $query = Event::query()
            ->upcoming()
            ->with(['venue', 'hostOrganisation', 'disciplines'])
            ->where('starts_at', '<=', $to)
            ->where(function (Builder $window) use ($from): void {
                $window->where('starts_at', '>=', $from)
                    ->orWhere(function (Builder $spans) use ($from): void {
                        $spans->whereNotNull('ends_at')->where('ends_at', '>=', $from);
                    });
            })
            ->orderBy('starts_at');

        if ($province instanceof Province) {
            self::inProvince($query, $province);
        }

        return $query->get();
    }

    /**
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    private static function inProvince(Builder $query, Province $province): Builder
    {
        return $query->where(function (Builder $placed) use ($province): void {
            $placed->whereHas('venue', fn (Builder $venue) => $venue->where('province', $province))
                ->orWhere(function (Builder $hosted) use ($province): void {
                    $hosted->whereNull('venue_id')
                        ->whereHas('hostOrganisation', fn (Builder $org) => $org->where('province', $province));
                });
        });
    }

    /**
     * @param  Collection<int, Event>  $events
     * @return list<string>
     */
    private static function sentences(Collection $events): array
    {
        return $events->take(6)->map(function (Event $event): string {
            $when = $event->starts_at?->timezone((string) config('app.timezone'))->format('j M Y') ?? 'a date to be confirmed';
            $host = $event->hostOrganisation?->name ?? 'the organiser';
            $venue = $event->venue?->name ?? 'a venue still to be confirmed';
            $town = $event->venue?->town;
            $place = filled($town) ? $venue.' in '.$town : $venue;

            return $event->title.' is hosted by '.$host.' at '.$place.' on '.$when.'.';
        })->all();
    }

    /**
     * @param  Collection<int, Event>  $events
     */
    private static function spanLabel(Collection $events): ?string
    {
        $starts = $events->map(fn (Event $event) => $event->starts_at)->filter();

        if ($starts->isEmpty()) {
            return null;
        }

        return SaturdayWeekend::label($starts->min(), $starts->max());
    }

    /**
     * @param  Collection<int, Event>  $events
     * @param  Collection<int, Event>  $later
     * @param  list<string>  $paragraphs
     * @return array<string, mixed>
     */
    private static function payload(
        string $title,
        string $description,
        string $canonical,
        ?string $robots,
        Collection $events,
        Collection $later,
        array $paragraphs,
        ?string $provinceLabel,
    ): array {
        $groups = $events
            ->groupBy(fn (Event $event): string => $event->venue?->province?->value
                ?? $event->hostOrganisation?->province?->value
                ?? 'unknown')
            ->map(function (Collection $group, string $key) use ($provinceLabel): array {
                $label = Province::tryFrom($key)?->getLabel() ?? $provinceLabel ?? 'Province to be confirmed';

                return [
                    'label' => $label,
                    'events' => $group->map(fn (Event $event): array => self::row($event))->values()->all(),
                ];
            })
            ->sortBy('label')
            ->values()
            ->all();

        $items = $events->map(fn (Event $event): array => [
            'name' => $event->title,
            'url' => $event->publicUrl(),
            'id' => $event->schemaId(),
        ])->all();

        return [
            'title' => $title,
            'description' => Seo::clip($description),
            'canonical' => $canonical,
            'robots' => $robots,
            'paragraphs' => $paragraphs,
            'groups' => $groups,
            'later' => $later->map(fn (Event $event): array => self::row($event))->values()->all(),
            'jsonLd' => [JsonLd::itemList($items, $title)],
        ];
    }

    /**
     * @return array{url: string, title: string, summary: string}
     */
    private static function row(Event $event): array
    {
        $when = $event->starts_at?->timezone((string) config('app.timezone'))->format('j M Y');

        return [
            'url' => $event->publicUrl(),
            'title' => $event->title,
            'summary' => collect([
                $event->hostOrganisation?->name,
                $event->venue?->name,
                $event->venue?->town,
                $when,
            ])->filter()->implode(', '),
        ];
    }

    private static function weekendUrl(?Province $province, bool $next): string
    {
        if ($province === null) {
            return route('answers.this-weekend');
        }

        return $next
            ? route('answers.province-next-weekend', $province->urlSlug())
            : route('answers.province-this-weekend', $province->urlSlug());
    }
}
