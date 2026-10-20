<?php

namespace App\Support;

use App\Enums\MembershipStatus;
use App\Models\Organisation;
use App\Models\Provider;
use App\Models\User;
use App\Models\Venue;

/**
 * One Shooter account can wear up to four extra hats: club, shop,
 * range, and media partner. The desk home, the public /me page, and
 * the mobile /api/v1/me endpoint all render the same capability
 * cards, so the data shape lives here instead of being built three
 * times.
 *
 * Each card is deliberately light: a title, a one-line summary, a
 * short list of the things the user actually manages, and a single
 * "Open on the website" link into the matching desk resource. All
 * heavy editing (members, invoices, magazine, billing, Paystack)
 * stays in Filament; the app and /me page only present the hub.
 */
final class DeskCapabilities
{
    /**
     * @return list<array{
     *     kind: string,
     *     title: string,
     *     summary: string,
     *     items: list<array{name: string, url: string}>,
     *     primary_action: array{label: string, url: string},
     * }>
     */
    public static function for(User $user): array
    {
        $cards = [];

        $clubs = $user->managedOrganisations()
            ->select(['organisations.id', 'organisations.name', 'organisations.slug'])
            ->orderBy('organisations.name')
            ->get();

        if ($clubs->isNotEmpty()) {
            $cards[] = [
                'kind' => 'club',
                'title' => 'Clubs',
                'summary' => trans_choice(':count club you manage|:count clubs you manage', $clubs->count(), ['count' => $clubs->count()]),
                'items' => $clubs
                    ->map(fn (Organisation $club): array => [
                        'name' => $club->name,
                        'url' => route('clubs.show', $club->slug),
                    ])
                    ->take(5)
                    ->all(),
                'primary_action' => [
                    'label' => 'Open clubs on the desk',
                    'url' => url('/desk/organisations'),
                ],
            ];
        }

        $shops = $user->managedProviders()
            ->select(['providers.id', 'providers.name', 'providers.slug'])
            ->orderBy('providers.name')
            ->get();

        if ($shops->isNotEmpty() || $user->isSupplier()) {
            $items = $shops
                ->map(fn (Provider $shop): array => [
                    'name' => $shop->name,
                    'url' => route('suppliers.show', $shop->slug),
                ])
                ->take(5)
                ->all();

            // isSupplier() covers the legacy `claimed_by` path for
            // shops that pre-date the provider_user pivot — surface
            // them so the card isn't blank for an existing owner.
            if ($items === [] && $user->isSupplier()) {
                $items = $user->providers()
                    ->orderBy('name')
                    ->get(['id', 'name', 'slug'])
                    ->map(fn (Provider $shop): array => [
                        'name' => $shop->name,
                        'url' => route('suppliers.show', $shop->slug),
                    ])
                    ->take(5)
                    ->all();
            }

            $cards[] = [
                'kind' => 'shop',
                'title' => 'Shops',
                'summary' => trans_choice(':count business you list|:count businesses you list', max(1, count($items)), ['count' => count($items)]),
                'items' => $items,
                'primary_action' => [
                    'label' => 'Edit your business',
                    'url' => route('suppliers.onboard'),
                ],
            ];
        }

        $ranges = $user->managedVenues()
            ->select(['venues.id', 'venues.name', 'venues.slug'])
            ->orderBy('venues.name')
            ->get();

        if ($ranges->isNotEmpty()) {
            $cards[] = [
                'kind' => 'range',
                'title' => 'Ranges',
                'summary' => trans_choice(':count range you manage|:count ranges you manage', $ranges->count(), ['count' => $ranges->count()]),
                'items' => $ranges
                    ->map(fn (Venue $range): array => [
                        'name' => $range->name,
                        'url' => route('ranges.show', $range->slug),
                    ])
                    ->take(5)
                    ->all(),
                'primary_action' => [
                    'label' => 'Open ranges on the desk',
                    'url' => url('/desk/venues'),
                ],
            ];
        }

        if ($user->is_media_partner === true) {
            $cards[] = [
                'kind' => 'media',
                'title' => 'Media',
                'summary' => 'Articles and magazine issues you contribute',
                'items' => [],
                'primary_action' => [
                    'label' => 'Open the media desk',
                    'url' => url('/desk/articles'),
                ],
            ];
        }

        return $cards;
    }

    /**
     * Flat list of capability kinds. Handy for `/api/v1/me` so the
     * mobile client can decide which tabs or cards to render without
     * downloading the whole payload.
     *
     * @return list<string>
     */
    public static function kindsFor(User $user): array
    {
        $kinds = [];

        if ($user->managedOrganisations()->exists()) {
            $kinds[] = 'club';
        }

        if ($user->managedProviders()->exists() || $user->isSupplier()) {
            $kinds[] = 'shop';
        }

        if ($user->venueMemberships()->where('status', MembershipStatus::Approved->value)->exists()) {
            $kinds[] = 'range';
        }

        if ($user->is_media_partner === true) {
            $kinds[] = 'media';
        }

        return $kinds;
    }
}
