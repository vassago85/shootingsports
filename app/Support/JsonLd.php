<?php

namespace App\Support;

use App\Enums\EventStatus;
use App\Models\Event;
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
        $calendarUrl = route('calendar');

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
                    'potentialAction' => [
                        '@type' => 'SearchAction',
                        'target' => [
                            '@type' => 'EntryPoint',
                            'urlTemplate' => $calendarUrl.'?q={search_term_string}',
                        ],
                        'query-input' => 'required name=search_term_string',
                    ],
                ],
                [
                    '@type' => 'SportsOrganization',
                    '@id' => $siteUrl.'#organization',
                    'url' => $siteUrl,
                    'name' => 'Shooting Sports',
                    'description' => 'The independent national register of South African shooting sport: clubs, ranges, suppliers and every match on the calendar.',
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

        $payload = [
            '@context' => 'https://schema.org',
            '@type' => 'SportsEvent',
            '@id' => $event->schemaId(),
            'name' => $event->title,
            'url' => $event->publicUrl(),
            'startDate' => self::iso($event->starts_at),
            'endDate' => self::endDateFor($event),
            'eventStatus' => self::eventStatus($event->status),
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'description' => $event->description ?: $event->locationLabel(),
            // Google Search Console (Rich Results) flags Events without
            // `performer` and `offers` as non-critical issues. Both are
            // populated below with sensible defaults so every event
            // renders as valid structured data even when the underlying
            // record has no explicit performers list or entry fee.
            'performer' => self::performerFor($event),
            'offers' => self::offersFor($event),
        ];

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

        return $payload;
    }

    /**
     * Best-effort endDate for the Event schema. Google requires this
     * field even for single-session events. Order of preference:
     *   1. Explicit ends_at on the model.
     *   2. all_day = true  -> end-of-day on starts_at.
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

        if ($event->ends_at) {
            return self::iso($event->ends_at);
        }

        if ($event->all_day) {
            return self::iso($event->starts_at->endOfDay());
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
     * Google requires `performer` even for events without an explicit
     * roster. For open competitions the host organisation is the
     * closest honest fit — they're the group putting on the event and
     * fielding stage crew / RSOs. Falls back to a generic PerformingGroup
     * when we have no host on record (imported events with only a venue).
     *
     * @return array<string, mixed>
     */
    private static function performerFor(Event $event): array
    {
        if ($event->hostOrganisation) {
            return [
                '@type' => 'SportsOrganization',
                'name' => $event->hostOrganisation->name,
                'url' => $event->hostOrganisation->isFederationListing()
                    ? route('federations.show', $event->hostOrganisation->slug)
                    : route('clubs.show', $event->hostOrganisation->slug),
            ];
        }

        return [
            '@type' => 'PerformingGroup',
            'name' => 'Competitive shooters',
        ];
    }

    /**
     * Google requires `offers` on Event. Emit url + availability always;
     * only include price when we actually know the fee. A fabricated
     * "0.00" claims free entry in rich results and is usually wrong.
     *
     * @return array<string, mixed>
     */
    private static function offersFor(Event $event): array
    {
        $priceCents = $event->member_fee_cents ?? $event->entry_fee_cents;

        // validFrom must be in the past for Google to accept the
        // offer as "buyable now". Use the event's created_at when
        // available so historical offers make sense in the timeline.
        $validFrom = self::iso($event->created_at ?? now());

        $offer = [
            '@type' => 'Offer',
            'url' => $event->entry_url ?: $event->publicUrl(),
            'priceCurrency' => 'ZAR',
            'availability' => self::offerAvailability($event),
            'validFrom' => $validFrom,
        ];

        if ($priceCents !== null) {
            $offer['price'] = number_format(((int) $priceCents) / 100, 2, '.', '');
        }

        return $offer;
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

    private static function offerAvailability(Event $event): string
    {
        // Match the outer eventStatus for cancelled / postponed events
        // so structured data doesn't contradict itself.
        if ($event->status === EventStatus::Cancelled) {
            return 'https://schema.org/Discontinued';
        }

        if ($event->status === EventStatus::Postponed) {
            return 'https://schema.org/PreOrder';
        }

        $capacity = (int) ($event->capacity ?? 0);
        $taken = (int) ($event->entries_taken ?? 0);

        if ($capacity > 0 && $taken >= $capacity) {
            return 'https://schema.org/SoldOut';
        }

        if ($capacity > 0 && $taken >= (int) ($capacity * 0.9)) {
            return 'https://schema.org/LimitedAvailability';
        }

        return 'https://schema.org/InStock';
    }

    /**
     * @return array<string, mixed>
     */
    public static function organisation(Organisation $organisation): array
    {
        $placed = filled($organisation->town) || $organisation->province !== null;

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => $placed ? ['SportsOrganization', 'SportsActivityLocation'] : 'SportsOrganization',
            '@id' => $organisation->schemaId(),
            'name' => $organisation->name,
            'url' => explode('#', $organisation->schemaId(), 2)[0],
            'sport' => self::sportLabel($organisation),
            'logo' => $organisation->logoUrl(),
            'sameAs' => array_values(array_filter([
                $organisation->website_url,
                $organisation->facebook_url,
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
            '@type' => 'SportsActivityLocation',
            '@id' => $venue->schemaId(),
            'name' => $venue->name,
            'url' => route('ranges.show', $venue->slug),
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

    private static function eventStatus(?EventStatus $status): string
    {
        return match ($status) {
            EventStatus::Cancelled => 'https://schema.org/EventCancelled',
            EventStatus::Postponed => 'https://schema.org/EventPostponed',
            default => 'https://schema.org/EventScheduled',
        };
    }
}
