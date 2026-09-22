<x-mockups.layout title="New match" active="account">
    <div class="wrap" style="padding-bottom:120px">
        <header class="mk-pagehead">
            <p class="label">Club desk</p>
            <h1>New match for {{ $club['name'] ?? 'your club' }}</h1>
            <p class="mk-lede">Preview only. Saving would list this match on the list, calendar, map, sport, club and range in one go — nothing is written yet.</p>
        </header>

        @include('mockups.partials.manage-nav', ['active' => 'new-match', 'club' => $club])

        <p class="mk-support" style="margin:12px 0 0">One listing here shows on the public
            <a class="mk-textlink" href="{{ $mk('mockups.matches') }}">match list</a>,
            <a class="mk-textlink" href="{{ $mk('mockups.matches.calendar') }}">calendar</a>,
            <a class="mk-textlink" href="{{ $mk('mockups.matches.map') }}">map</a>,
            @if ($club)
                <a class="mk-textlink" href="{{ $mk('mockups.club', ['slug' => $club['slug']]) }}">{{ $club['name'] }} page</a>,
            @endif
            the sport page and the range page. Nothing is duplicated.
        </p>

        @include('mockups.partials.match-editor', [
            'match' => null,
            'sports' => $sports,
            'ranges' => $ranges,
            'clubs' => $clubs,
            'asOrganiser' => true,
        ])
    </div>
</x-mockups.layout>
