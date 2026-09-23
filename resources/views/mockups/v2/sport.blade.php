<x-mockups.v2.layout :title="data_get($sport, 'name', 'Sport')" active="sports">
    <div class="v2-wrap v2-page">
        @if (! $sport)
            <p class="v2-empty"><strong>No published sports in the register yet.</strong></p>
        @else
            @php
                $following = $follows['sports']->contains($sport['slug']);
                $nextSports = $following
                    ? $follows['sports']->reject(fn ($s) => $s === $sport['slug'])->values()
                    : $follows['sports']->push($sport['slug'])->unique()->values();
                $followQuery = array_filter([
                    'sport' => $nextSports->all(),
                    'club' => $follows['clubs']->all(),
                    'province' => $follows['province'],
                ], fn ($value) => $value !== '' && $value !== []);
            @endphp
            <header style="padding-top:28px">
                @if (filled($sport['family_label']))<p class="v2-kicker">{{ $sport['family_label'] }}</p>@endif
                <h1>{{ $sport['name'] }}</h1>
                @if (filled($sport['blurb']))<p class="v2-lede">{{ $sport['blurb'] }}</p>@endif
                <div class="v2-actions">
                    <a class="v2-btn v2-btn-line" href="{{ $mk('mockups.v2.sport', array_merge(['slug' => $sport['slug']], $followQuery)) }}" aria-pressed="{{ $following ? 'true' : 'false' }}">{{ $following ? 'Following' : 'Follow' }}</a>
                    <a class="v2-btn v2-btn-green" href="{{ $mk('mockups.v2.matches', ['sport' => $sport['slug']]) }}">Find a match</a>
                </div>
            </header>
            @include('mockups.v2.partials.ad-space', ['sponsors' => $sponsors, 'limit' => $sponsors->count()])
            @if (($sport['children'] ?? []) !== [])
                <section class="v2-section">
                    <h2>Choose a sub-discipline</h2>
                    <p class="v2-lede">The match list opens after you pick one.</p>
                    <div class="v2-list" style="margin-top:12px">
                        @foreach ($sport['children'] as $child)
                            <a class="v2-row" style="grid-template-columns:minmax(0,1fr) auto 16px" href="{{ $mk('mockups.v2.matches', ['sport' => $child['slug']]) }}">
                                <span><span class="v2-name">{{ $child['name'] }}</span>@if(filled($child['blurb']))<span class="v2-sport">{{ $child['blurb'] }}</span>@endif</span>
                                <span class="v2-side">{{ $child['upcoming_count'] }} upcoming</span>
                                <span class="v2-chev">›</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
            @if ($sport['upcoming'] !== [])
                <section class="v2-section">
                    <div class="v2-headrow"><h2>Upcoming matches</h2><a href="{{ $mk('mockups.v2.matches', ['sport' => $sport['slug']]) }}">All matches</a></div>
                    <div class="v2-list">@foreach ($sport['upcoming'] as $match)<x-mockups.v2.match-row :match="$match" />@endforeach</div>
                </section>
            @endif
            @if ($sport['clubs'] !== [] || $sport['ranges'] !== [])
                <section class="v2-section">
                    <h2>Where to shoot</h2>
                    <div class="v2-list" style="margin-top:12px">
                        @foreach ($sport['clubs'] as $club)
                            <a class="v2-row" style="grid-template-columns:minmax(0,1fr) auto 16px" href="{{ $mk('mockups.v2.club', ['slug' => $club['slug']]) }}"><span class="v2-name">{{ $club['name'] }}</span><span class="v2-side">{{ collect([$club['place'] ?? null])->filter(fn (?string $part): bool => filled($part) && $part !== '—')->implode('') }}</span><span class="v2-chev">›</span></a>
                        @endforeach
                        @foreach ($sport['ranges'] as $range)
                            <a class="v2-row" style="grid-template-columns:minmax(0,1fr) auto 16px" href="{{ $mk('mockups.v2.range', ['slug' => $range['slug']]) }}"><span class="v2-name">{{ $range['name'] }}</span><span class="v2-side">{{ collect([$range['place'] ?? null])->filter(fn (?string $part): bool => filled($part) && $part !== '—')->implode('') }}</span><span class="v2-chev">›</span></a>
                        @endforeach
                    </div>
                </section>
            @endif
            @php
                $learn = collect([
                    ($sport['paragraphs'][0] ?? null) ? ['What is '.$sport['name'].'?', $sport['paragraphs'][0]] : null,
                    count(array_slice($sport['paragraphs'], 1)) ? ['How does a match work?', implode(' ', array_slice($sport['paragraphs'], 1))] : null,
                    $sport['equipment'] ? ['What equipment do I need?', $sport['equipment']] : null,
                    $sport['beginner_paragraph'] ? ['Can beginners participate?', $sport['beginner_paragraph']] : null,
                    $sport['fees'] !== [] ? ['Typical costs', implode(', ', $sport['fees'])] : null,
                ])->filter();
            @endphp
            @if ($learn->isNotEmpty() || $sport['federation'] || $sport['related'] !== [])
                <section class="v2-section">
                    <h2>Learn about {{ $sport['name'] }}</h2>
                    @foreach ($learn as [$heading, $copy])
                        <h3 style="margin-top:16px">{{ $heading }}</h3>
                        <div class="v2-prose"><p>{{ $copy }}</p></div>
                    @endforeach
                    @if ($sport['federation'])
                        <h3 style="margin-top:16px">Governing organisation</h3>
                        <p><a class="v2-textlink" href="{{ $mk('mockups.v2.club', ['slug' => $sport['federation']['slug']]) }}">{{ $sport['federation']['name'] }}</a></p>
                    @endif
                    @if ($sport['related'] !== [])
                        <h3 style="margin-top:16px">Related sports</h3>
                        <div class="v2-tags">@foreach ($sport['related'] as $related)<a class="v2-tag" href="{{ $mk('mockups.v2.sport', ['slug' => $related['slug']]) }}">{{ $related['name'] }}</a>@endforeach</div>
                    @endif
                </section>
            @endif
        @endif
    </div>
</x-mockups.v2.layout>
