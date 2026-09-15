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
                    'alternateName' => 'The SA Register of Shooting Sport',
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
            'eventStatus' => self::eventStatus($event->status),
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'description' => $event->description ?: $event->locationLabel(),
        ];

        if ($event->ends_at) {
            $payload['endDate'] = $event->ends_at->toIso8601String();
        }

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
