@php
    $ios = $platform === 'ios';
    $store = $ios ? 'Apple' : 'Google Play';
    $billedBy = $ios ? 'your Apple ID' : 'your Google Play account';
    $manageUrl = $ios
        ? 'https://apps.apple.com/account/subscriptions'
        : 'https://play.google.com/store/account/subscriptions';
    $manageLabel = $ios ? 'Manage in Apple Subscriptions' : 'Manage in Google Play';
    $length = $cycle === 'annual' ? '1 year' : '1 month';
    $plan = $pricing[$cycle];
    $freeFollows = config('plans.free.follows');
    $freeSearches = config('plans.free.saved_searches');
    $freeHistory = config('plans.free.history_months');
    $freeLog = config('plans.free.attended_events_slots');
    $place = $match
        ? collect([$match['range'], $match['town'], $match['province']])->filter()->implode(' · ')
        : '';
    $matchSponsor = $sponsors->first(function (array $sponsor) use ($match): bool {
        return $match !== null && array_intersect($sponsor['discipline_slugs'], $match['discipline_slugs']) !== [];
    }) ?? $sponsors->first();
    $kmAway = function (array $row): ?string {
        if (! is_numeric($row['distance_km'] ?? null)) {
            return null;
        }

        $km = (float) $row['distance_km'];

        return $km < 1 ? 'Under 1 km' : round($km).' km';
    };
    $familyIcon = function (?string $family): string {
        return match ($family) {
            'handgun' => 'handgun',
            'shotgun' => 'shotgun',
            'airgun' => 'airgun',
            'multi' => 'multi',
            default => 'rifle',
        };
    };
@endphp

@if (in_array($screen, ['splash', 'welcome', 'location', 'sports-choose', 'follow-onboard', 'alerts-onboard', 'ready'], true))
    @include('mockups.partials.app-first-run')
@elseif ($screen === 'home')
    @include('mockups.partials.app-home')
@elseif (in_array($screen, ['matches', 'calendar', 'search'], true))
    @include('mockups.partials.app-discovery')
@elseif (in_array($screen, ['match', 'pack', 'packing'], true))
    @include('mockups.partials.app-match-journey')
@elseif (in_array($screen, ['find', 'clubs', 'club', 'ranges', 'range', 'sports', 'sport'], true))
    @include('mockups.partials.app-explore')
@elseif (in_array($screen, ['suppliers', 'supplier'], true))
    @include('mockups.partials.app-suppliers')
@elseif (in_array($screen, ['you', 'following', 'appearance', 'alerts'], true))
    @include('mockups.partials.app-account')
@elseif (in_array($screen, ['subscription', 'cancel', 'cancelled', 'plans'], true))
    @include('mockups.partials.app-subscription')
@elseif (in_array($screen, ['data', 'delete', 'deleted', 'permissions', 'signin'], true))
    @include('mockups.partials.app-privacy')
@endif
