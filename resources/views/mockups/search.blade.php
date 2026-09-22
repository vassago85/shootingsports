<x-mockups.layout title="Search" active="home">
    <div class="wrap" style="padding-bottom:48px">
        <header class="mk-pagehead">
            <p class="label">Search</p>
            <h1>Search the register</h1>
            <p class="mk-lede">Matches, sports, clubs, ranges and industry.</p>
        </header>
        <form class="mk-filters" method="get" action="{{ $mk('mockups.search') }}">
            @if (request('device') === 'mobile')
                <input type="hidden" name="device" value="mobile">
            @endif
            <label class="field grow">
                <span>Search</span>
                <input type="search" name="q" value="{{ $q }}" placeholder="IPSC, Pretoria, ELR, optics" autofocus>
            </label>
            <button class="btn" type="submit">Search</button>
        </form>
        @if ($q === '')
            <p class="mk-support">Showing a few from each part of the register. Type to search all of them.</p>
        @endif
        @foreach ($groups as $heading => $rows)
            <section class="mk-section">
                <h2>{{ $heading }}</h2>
                @forelse ($rows as $row)
                    @if ($heading === 'Matches')
                        <x-mockups.match-row :match="$row" />
                    @elseif ($heading === 'Sports')
                        <a class="mk-row" href="{{ $mk('mockups.sport', ['slug' => $row['slug']]) }}"><span class="mk-row-title">{{ $row['name'] }}</span><span class="mk-row-meta">{{ $row['blurb'] }}</span></a>
                    @elseif ($heading === 'Clubs')
                        <a class="mk-row" href="{{ $mk('mockups.club', ['slug' => $row['slug']]) }}"><span class="mk-row-title">{{ $row['name'] }}</span><span class="mk-row-meta">{{ $row['place'] }}</span></a>
                    @elseif ($heading === 'Ranges')
                        <a class="mk-row" href="{{ $mk('mockups.range', ['slug' => $row['slug']]) }}"><span class="mk-row-title">{{ $row['name'] }}</span><span class="mk-row-meta">{{ $row['place'] }}</span></a>
                    @else
                        <a class="mk-row" href="{{ $mk('mockups.business', ['slug' => $row['slug']]) }}"><span class="mk-row-title">{{ $row['name'] }}</span><span class="mk-row-meta">{{ $row['category'] }} · {{ $row['place'] }}</span></a>
                    @endif
                @empty
                    <p>No {{ strtolower($heading) }} for that search.</p>
                @endforelse
            </section>
        @endforeach
    </div>
</x-mockups.layout>
