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
            '@type' => 'Event',
            'name' => $event->title,
            'url' => $event->publicUrl(),
            'startDate' => $event->starts_at?->toIso8601String(),
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
            $payload['organizer'] = self::organisation($event->hostOrganisation);
        }

        if ($event->venue) {
            $payload['location'] = self::venue($event->venue);
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
            return $event->ends_at->toIso8601String();
        }

        if ($event->all_day) {
            return $event->starts_at->endOfDay()->toIso8601String();
        }

        return $event->starts_at->copy()->addHours(4)->toIso8601String();
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
     * Google requires `offers` on Event even for free entry. Emits a
     * single Offer with the cheapest advertised price (member fee
     * beats standard entry fee when both are present), the entry URL
     * or event page as the buy link, and availability derived from
     * capacity vs entries_taken.
     *
     * Cancelled / postponed events get availability that matches their
     * schema.org eventStatus so the two signals never contradict each
     * other in Search Console.
     *
     * @return array<string, mixed>
     */
    private static function offersFor(Event $event): array
    {
        // Cheapest advertised price: prefer member fee (usually the
        // lower one). Falls back to standard entry fee, then to 0.00
        // for events with no fee on record — Google accepts free
        // events but the field must still be present.
        $priceCents = $event->member_fee_cents ?? $event->entry_fee_cents ?? 0;
        $price = number_format($priceCents / 100, 2, '.', '');

        // validFrom must be in the past for Google to accept the
        // offer as "buyable now". Use the event's created_at when
        // available so historical offers make sense in the timeline.
        $validFrom = ($event->created_at ?? now())->toIso8601String();

        return [
            '@type' => 'Offer',
            'url' => $event->entry_url ?: $event->publicUrl(),
            'price' => $price,
            'priceCurrency' => 'ZAR',
            'availability' => self::offerAvailability($event),
            'validFrom' => $validFrom,
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
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'SportsOrganization',
            'name' => $organisation->name,
            'url' => $organisation->isFederationListing()
                ? route('federations.show', $organisation->slug)
                : route('clubs.show', $organisation->slug),
            'logo' => $organisation->logoUrl(),
            'sameAs' => array_values(array_filter([
                $organisation->website_url,
                $organisation->facebook_url,
            ])),
            'address' => array_filter([
                '@type' => 'PostalAddress',
                'addressLocality' => $organisation->town,
                'addressRegion' => $organisation->province?->getLabel(),
                'addressCountry' => 'ZA',
            ]),
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
            'name' => $venue->name,
            'url' => route('ranges.show', $venue->slug),
            'address' => array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => $venue->address,
                'addressLocality' => $venue->town,
                'addressRegion' => $venue->province?->getLabel(),
                'addressCountry' => 'ZA',
            ]),
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
            'description' => $provider->description,
            'address' => array_filter([
                '@type' => 'PostalAddress',
                'addressLocality' => $provider->town,
                'addressRegion' => $provider->province?->getLabel(),
                'addressCountry' => 'ZA',
            ]),
        ]);
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
