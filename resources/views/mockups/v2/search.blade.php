<x-mockups.v2.layout title="Search" active="home">
    <div class="v2-wrap v2-page">
        <header class="v2-hero">
            <div class="v2-hero-copy">
                <p class="v2-kicker">Search</p>
                <h1>Search the register</h1>
                <p class="v2-lede">Matches, sports, clubs, ranges and industry.</p>
            </div>
        </header>
        <form class="v2-filters cols-3" method="get" action="{{ $mk('mockups.v2.search') }}">
            @include('mockups.v2.partials.keep')
            <label class="v2-field" style="grid-column:1 / -2"><span>Search</span><input type="search" name="q" value="{{ $q }}" placeholder="IPSC, Pretoria, ELR, optics" autofocus></label>
            <button class="v2-btn v2-btn-green" type="submit">Search</button>
        </form>
        @foreach ($groups as $heading => $rows)
            @if ($rows->isNotEmpty())
                <section class="v2-section">
                    <h2>{{ $heading }}</h2>
                    <div class="v2-list" style="margin-top:10px">
                        @foreach ($rows as $row)
                            @if ($heading === 'Matches')
                                <x-mockups.v2.match-row :match="$row" />
                            @elseif ($heading === 'Sports')
                                <a class="v2-row" style="grid-template-columns:minmax(0,1fr) 16px" href="{{ $mk('mockups.v2.sport', ['slug' => $row['slug']]) }}"><span class="v2-name">{{ $row['name'] }}@if(filled($row['blurb']))<span class="v2-sport">{{ $row['blurb'] }}</span>@endif</span><span class="v2-chev">›</span></a>
                            @elseif ($heading === 'Clubs')
                                <x-mockups.v2.club-row :club="$row" />
                            @elseif ($heading === 'Ranges')
                                <x-mockups.v2.range-row :range="$row" />
                            @else
                                <a class="v2-row" style="grid-template-columns:minmax(0,1fr) auto 16px" href="{{ $mk('mockups.v2.business', ['slug' => $row['slug']]) }}"><span class="v2-name">{{ $row['name'] }}</span><span class="v2-side">{{ collect([$row['category'] ?? null, $row['place'] ?? null])->filter(fn (?string $part): bool => filled($part) && $part !== '—')->implode(' · ') }}</span><span class="v2-chev">›</span></a>
                            @endif
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach
    </div>
</x-mockups.v2.layout>
