@php
    $followsQuery = array_filter([
        'sport' => $follows['sports']->all(),
        'club' => $follows['clubs']->all(),
        'province' => $follows['province'],
    ], fn ($value) => $value !== '' && $value !== []);

    /** Build the URL with $slug toggled inside the sport[] / club[] / province query. */
    $toggle = function (string $key, string $value) use ($follows): array {
        $sports = $follows['sports']->values();
        $clubs = $follows['clubs']->values();
        $province = $follows['province'];

        if ($key === 'sport') {
            $sports = $sports->contains($value) ? $sports->reject(fn ($slug) => $slug === $value)->values() : $sports->push($value)->unique()->values();
        }

        if ($key === 'club') {
            $clubs = $clubs->contains($value) ? $clubs->reject(fn ($slug) => $slug === $value)->values() : $clubs->push($value)->unique()->values();
        }

        if ($key === 'province') {
            $province = $province === $value ? '' : $value;
        }

        return array_filter([
            'sport' => $sports->all(),
            'club' => $clubs->all(),
            'province' => $province,
        ], fn ($v) => $v !== '' && $v !== []);
    };
@endphp
<x-mockups.layout title="Following" active="account">
    <div class="wrap" style="padding-bottom:48px">
        <header class="mk-pagehead">
            <p class="label">Account</p>
            <h1>Following</h1>
            <p class="mk-lede">Sports, clubs and one province drive My Shooting. Ranges are not followable yet, so they are not offered here.</p>
        </header>

        <nav class="mk-views" aria-label="Account">
            <a href="{{ $mk('mockups.account', $followsQuery) }}">My Shooting</a>
            <a class="on" href="{{ $mk('mockups.account.following', $followsQuery) }}">Following</a>
            <a href="{{ $mk('mockups.onboarding') }}">Set up</a>
        </nav>

        <section class="mk-section">
            <h2>Sports</h2>
            <p class="mk-support">{{ $follows['sports']->count() }} followed. Toggle a sport to add or remove it from My Shooting.</p>
            <div class="mk-chips">
                @foreach ($sportOptions as $sport)
                    @php $on = $follows['sports']->contains($sport['slug']); @endphp
                    <a class="mk-chip @if ($on) on @endif" href="{{ $mk('mockups.account.following', $toggle('sport', $sport['slug'])) }}" aria-pressed="{{ $on ? 'true' : 'false' }}">
                        {{ $on ? '✓ ' : '' }}{{ $sport['name'] }}
                    </a>
                @endforeach
            </div>
        </section>

        <section class="mk-section">
            <h2>Clubs</h2>
            <p class="mk-support">{{ $follows['clubs']->count() }} followed. Clubs include series and associations.</p>
            <div class="mk-chips">
                @foreach ($clubOptions->take(24) as $club)
                    @php $on = $follows['clubs']->contains($club['slug']); @endphp
                    <a class="mk-chip @if ($on) on @endif" href="{{ $mk('mockups.account.following', $toggle('club', $club['slug'])) }}" aria-pressed="{{ $on ? 'true' : 'false' }}">
                        {{ $on ? '✓ ' : '' }}{{ $club['name'] }}
                    </a>
                @endforeach
            </div>
            @if ($clubOptions->count() > 24)
                <p class="mk-support" style="margin-top:12px">First 24 of {{ $clubOptions->count() }}. In production the list would be searchable.</p>
            @endif
        </section>

        <section class="mk-section">
            <h2>Province</h2>
            <p class="mk-support">One province at a time. Matches in that province also show on My Shooting.</p>
            <div class="mk-chips">
                @foreach ($provinces as $province)
                    @php $on = $follows['province'] === $province->value; @endphp
                    <a class="mk-chip @if ($on) on @endif" href="{{ $mk('mockups.account.following', $toggle('province', $province->value)) }}" aria-pressed="{{ $on ? 'true' : 'false' }}">
                        {{ $on ? '✓ ' : '' }}{{ $province->getLabel() }}
                    </a>
                @endforeach
            </div>
        </section>

        <section class="mk-section">
            <h2>Preview</h2>
            <p>See how these follows shape My Shooting.</p>
            <div class="mk-actions">
                <a class="btn" href="{{ $mk('mockups.account', $followsQuery) }}">Open My Shooting</a>
                @if ($follows['sports']->isNotEmpty() || $follows['clubs']->isNotEmpty() || $follows['province'] !== '')
                    <a class="btn ghost" href="{{ $mk('mockups.account.following') }}">Clear all</a>
                @endif
            </div>
        </section>
    </div>
</x-mockups.layout>
