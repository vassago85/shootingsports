<?php

namespace App\Support;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\MagazineIssue;
use App\Models\Organisation;
use App\Models\Provider;
use App\Models\Venue;

class JsonLd
{
    /**
     * WebSite + SportsOrganization graph that always ships on public pages.
     * Rendered by the x-seo-meta component so every URL has a stable
     * site identity that Google, Bing and social crawlers can pick up.
     *
     * @return array<string, mixed>
     */
    public static function site(): array
    {
        $siteUrl = rtrim(url('/'), '/').'/';

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'WebSite',
                    '@id' => $siteUrl.'#website',
                    'url' => $siteUrl,
                    'name' => 'Shooting Sports',
                    'alternateName' => 'Find your sport, your club, your match',
                    'inLanguage' => 'en-ZA',
                ],
                [
                    '@type' => 'Organization',
                    '@id' => $siteUrl.'#organization',
                    'url' => $siteUrl,
                    'name' => 'Shooting Sports',
                    'description' => 'Shooting sports in South Africa. The independent national register of clubs, ranges, suppliers and every match on the calendar.',
                    'logo' => asset('images/og-default.png'),
                ],
                [
                    '@type' => 'SportsOrganization',
                    '@id' => $siteUrl.'#sports',
                    'url' => $siteUrl,
                    'name' => 'Shooting Sports',
                    'description' => 'Shooting sports in South Africa. The independent national register of clubs, ranges, suppliers and every match on the calendar.',
                    'logo' => asset('images/og-default.png'),
                    'sport' => 'Shooting sport',
                    'areaServed' => [
                        '@type' => 'Country',
                        'name' => 'South Africa',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function event(Event $event): array
    {
        $event->loadMissing(['hostOrganisation.parent', 'venue', 'banner']);
        $facts = PublicEventFacts::for($event);
        $description = $facts->machineDescription();

        $payload = [
            '@context' => 'https://schema.org',
            '@type' => 'SportsEvent',
            '@id' => $event->schemaId(),
            'name' => $event->title,
            'url' => $event->publicUrl(),
            'startDate' => self::startDateFor($event),
            'endDate' => self::endDateFor($event),
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'description' => $description !== '' ? $description : null,
            'offers' => self::offersFor($event),
        ];

        $status = self::eventStatus($event->status);

        if ($status !== null) {
            $payload['eventStatus'] = $status;
        }

        if ($event->hostOrganisation) {
            $organizer = self::organisation($event->hostOrganisation);
            unset($organizer['@context']);
            $payload['organizer'] = $organizer;
        }

        if ($event->venue) {
            $location = self::venue($event->venue);
            unset($location['@context']);
            $payload['location'] = $location;
        }

        if ($coverUrl = $event->coverImageUrl()) {
            $payload['image'] = $coverUrl;
        }

        if ($event->relationLoaded('partners') && $event->partners->isNotEmpty()) {
            $payload['sponsor'] = $event->partners
                ->map(fn (Provider $partner): array => array_filter([
                    '@type' => 'Organization',
                    'name' => $partner->name,
                    'url' => route('suppliers.show', $partner),
                    'logo' => $partner->logoUrl(),
                ]))
                ->values()
                ->all();
        }

        if (($payload['description'] ?? null) === null) {
            unset($payload['description']);
        }

        return $payload;
    }

    /**
     * startDate for the Event schema. All-day events emit a date-only
     * value ("YYYY-MM-DD") per Google's docs — otherwise Rich Results
     * reads "02:00 SAST" and marks the match as starting at 2am.
     */
    private static function startDateFor(Event $event): ?string
    {
        if ($event->starts_at === null) {
            return null;
        }

        if ($event->all_day) {
            return $event->starts_at->timezone(config('app.timezone'))->format('Y-m-d');
        }

        return self::iso($event->starts_at);
    }

    /**
     * Best-effort endDate for the Event schema. Google requires this
     * field even for single-session events. Order of preference:
     *   1. all_day = true -> date-only value, defaults to the same day
     *      when no explicit ends_at exists.
     *   2. Explicit ends_at on the model.
     *   3. Fallback: starts_at + 4 hours (typical open-format match).
     *
     * The fallback keeps the schema valid without inventing suspicious
     * multi-day windows — 4 hours is short enough that Google's "event
     * is over" detection still fires the same day, avoiding stale rich
     * results.
     */
    private static function endDateFor(Event $event): ?string
    {
        if ($event->starts_at === null) {
            return null;
        }

        if ($event->all_day) {
            $end = $event->ends_at ?? $event->starts_at;

            return $end->timezone(config('app.timezone'))->format('Y-m-d');
        }

        if ($event->ends_at) {
            return self::iso($event->ends_at);
        }

        return self::iso($event->starts_at->addHours(4));
    }

    /**
     * Always emit SAST (+02:00). Stored datetimes can serialise as UTC
     * (+00:00) when the connection/session timezone drifts — Google then
     * shows the wrong local start time on rich results.
     */
    private static function iso(mixed $dt): ?string
    {
        if ($dt === null) {
            return null;
        }

        return $dt->timezone(config('app.timezone'))->toIso8601String();
    }

    /**
     * One offer is an object. Two prices (general entry and member
     * entry) stay a list so the member price is not published as the
     * only admission price.
     *
     * @return array<string, mixed>|list<array<string, mixed>>
     */
    private static function offersFor(Event $event): array
    {
        $offers = PublicEventFacts::for($event)->schemaOffers();

        return count($offers) === 1 ? $offers[0] : $offers;
    }

    /**
     * BreadcrumbList schema for the SERP breadcrumb strip. Each item
     * is an associative array with 'name' and 'url' keys. Order
     * matters — position 1 is the closest to the root.
     *
     * Google's Rich Results docs are strict about a couple of things
     * this helper handles automatically:
     *   - Position must be a 1-indexed integer, contiguous.
     *   - Every item URL must be absolute. Callers are expected to
     *     pass absolute URLs already, but if a relative URL slips in
     *     the enclosing url() helper on the view side will have
     *     rendered it — nothing else to do here.
     *
     * @param  list<array{name: string, url: string}>  $items
     * @return array<string, mixed>
     */
    public static function breadcrumbs(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_values(array_map(
                fn (array $item, int $index): array => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $item['name'],
                    'item' => $item['url'],
                ],
                $items,
                array_keys($items),
            )),
        ];
    }

    /**
     * ItemList schema for collection pages (calendar, directory
     * indexes, discipline landings). Google uses this to show a
     * carousel-style rich result and to understand that a page is a
     * curated list of N pointers, not a single canonical entity.
     *
     * Each item is passed as a plain array (usually built from a
     * paginator slice) — this helper wraps the required boilerplate.
     *
     * @param  list<array{url: string, name?: string}>  $items
     * @return array<string, mixed>
     */
    public static function itemList(array $items, string $name): array
    {
        $elements = array_values(array_map(
            fn (array $item, int $index): array => array_filter([
                '@type' => 'ListItem',
                'position' => $index + 1,
                'url' => $item['url'],
                'name' => $item['name'] ?? null,
                'item' => filled($item['id'] ?? null) ? ['@id' => $item['id']] : null,
            ]),
            $items,
            array_keys($items),
        ));

        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $name,
            'numberOfItems' => count($elements),
            'itemListElement' => $elements,
        ];
    }

    /**
     * PublicationIssue schema for a single magazine issue, nested in
     * the "Targets" Periodical so Google understands the series.
     *
     * @return array<string, mixed>
     */
    public static function magazineIssue(MagazineIssue $issue): array
    {
        $periodical = [
            '@type' => 'Periodical',
            'name' => $issue->publication,
            'inLanguage' => $issue->language,
            'url' => route('magazines.index'),
        ];

        $payload = [
            '@context' => 'https://schema.org',
            '@type' => 'PublicationIssue',
            '@id' => route('magazines.show', $issue->slug).'#issue',
            'name' => $issue->title,
            'url' => route('magazines.show', $issue->slug),
            'inLanguage' => $issue->language,
            'isPartOf' => $periodical,
            'issueNumber' => $issue->issue_label,
            'numberOfPages' => $issue->page_count,
            'datePublished' => self::iso($issue->published_at),
            'description' => $issue->description,
            'image' => $issue->coverUrl(),
            'thumbnailUrl' => $issue->coverUrl(),
            'publisher' => ($label = $issue->publisherLabel()) !== null
                ? ['@type' => 'Organization', 'name' => $label]
                : null,
        ];

        return array_filter($payload, fn (mixed $value): bool => $value !== null && $value !== '');
    }

    public static function encode(mixed $value): string
    {
        return json_encode(
            $value,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_THROW_ON_ERROR,
        );
    }

    /**
     * ItemList of the published magazine issues on the index page.
     *
     * @param  list<MagazineIssue>  $issues
     * @return array<string, mixed>
     */
    public static function magazineIndex(array $issues): array
    {
        $items = array_map(
            fn (MagazineIssue $issue): array => [
                'url' => route('magazines.show', $issue->slug),
                'name' => $issue->title,
            ],
            $issues,
        );

        return self::itemList($items, 'Published magazines');
    }

    /**
     * @return array<string, mixed>
     */
    public static function organisation(Organisation $organisation): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'SportsOrganization',
            '@id' => $organisation->schemaId(),
            'name' => $organisation->name,
            'url' => explode('#', $organisation->schemaId(), 2)[0],
            'sport' => self::sportLabel($organisation),
            'logo' => $organisation->logoUrl(),
            'sameAs' => array_values(array_filter([
                $organisation->website_url,
                $organisation->facebook_url,
                $organisation->instagram_url,
                $organisation->whatsapp_url,
            ])),
            'address' => self::postalAddress(null, $organisation->town, $organisation->province?->getLabel()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function venue(Venue $venue): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => ['Place', 'SportsActivityLocation'],
            '@id' => $venue->schemaId(),
            'name' => $venue->name,
            'url' => route('ranges.show', $venue->slug),
            'image' => ($images = $venue->imageUrls()) !== [] ? $images : null,
            'sport' => self::sportLabel($venue),
            'address' => self::postalAddress($venue->address, $venue->town, $venue->province?->getLabel()),
            'geo' => ($venue->lat && $venue->lng) ? [
                '@type' => 'GeoCoordinates',
                'latitude' => (float) $venue->lat,
                'longitude' => (float) $venue->lng,
            ] : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function provider(Provider $provider): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => $provider->name,
            'url' => $provider->website_url ?: url()->current(),
            'description' => $provider->tagline ?: $provider->description,
            'image' => $provider->logoUrl(),
            'address' => self::postalAddress(null, $provider->town, $provider->province?->getLabel()),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function postalAddress(?string $street, ?string $locality, ?string $region): ?array
    {
        $address = array_filter([
            'streetAddress' => $street,
            'addressLocality' => $locality,
            'addressRegion' => $region,
        ], fn (mixed $value): bool => filled($value));

        if ($address === []) {
            return null;
        }

        return ['@type' => 'PostalAddress', 'addressCountry' => 'ZA', ...$address];
    }

    private static function sportLabel(object $record): ?string
    {
        if (! method_exists($record, 'relationLoaded') || ! $record->relationLoaded('disciplines')) {
            return null;
        }

        $label = $record->disciplines->pluck('name')->filter()->implode(', ');

        return $label !== '' ? $label : null;
    }

    private static function eventStatus(?EventStatus $status): ?string
    {
        return match ($status) {
            EventStatus::Cancelled => 'https://schema.org/EventCancelled',
            EventStatus::Postponed => 'https://schema.org/EventPostponed',
            EventStatus::Planned => null,
            default => 'https://schema.org/EventScheduled',
        };
    }
}
