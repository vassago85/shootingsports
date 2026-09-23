<x-mockups.layout title="{{ data_get($sport, 'name', 'Sport') }}" active="sports" :sample="data_get($sport, 'name')">
    <div class="wrap" style="padding-bottom:48px">
        @if (! $sport)
            <div class="mk-empty"><strong>No published sports in the register yet.</strong></div>
        @else
            <header class="mk-pagehead">
                <p class="label">{{ $sport['family_label'] }}</p>
                <h1>{{ $sport['name'] }}</h1>
                <p class="mk-lede">{{ $sport['blurb'] }}</p>
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
                <div class="mk-actions">
                    <a class="btn @if ($following) mk-follow is-on @else ghost @endif" href="{{ $mk('mockups.sport', array_merge(['slug' => $sport['slug']], $followQuery)) }}" aria-pressed="{{ $following ? 'true' : 'false' }}">{{ $following ? '✓ Following' : 'Follow' }}</a>
                    <a class="btn" href="{{ $mk('mockups.matches', ['sport' => $sport['slug']]) }}">Find a match</a>
                </div>
            </header>

            @php
                $clubCount = count($sport['clubs']);
                $rangeCount = count($sport['ranges']);
                $counts = collect([
                    count($sport['upcoming']) ? count($sport['upcoming']).' upcoming '.Str::plural('match', count($sport['upcoming'])) : null,
                    $clubCount ? $clubCount.' '.Str::plural('club', $clubCount) : null,
                    $rangeCount ? $rangeCount.' '.Str::plural('range', $rangeCount) : null,
                ])->filter();
            @endphp
            @if ($counts->isNotEmpty())
                <p class="mk-context">{{ $counts->implode(' · ') }}</p>
            @endif

            @include('mockups.partials.ad-space', ['sponsors' => $sponsors, 'limit' => $sponsors->count()])

            @if (($sport['children'] ?? []) !== [])
                <section class="mk-section">
                    <h2>Choose a sub-discipline</h2>
                    <p class="mk-lede">The match list opens after you pick one. Calendar is the other view on that list.</p>
                    <div class="mk-tiles" style="margin-top:18px">
                        @foreach ($sport['children'] as $child)
                            <a class="mk-tile" href="{{ $mk('mockups.matches', ['sport' => $child['slug']]) }}">
                                <span class="fam">Sub-discipline</span>
                                <strong>{{ $child['name'] }}</strong>
                                <span>{{ $child['blurb'] }}</span>
                                <em>Match list · {{ $child['upcoming_count'] }} upcoming</em>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($sport['upcoming'] !== [])
                <section class="mk-section">
                    <div class="mk-headrow">
                        <h2>Upcoming matches</h2>
                        <a class="mk-textlink" href="{{ $mk('mockups.matches', ['sport' => $sport['slug']]) }}">All matches</a>
                    </div>
                    @foreach ($sport['upcoming'] as $match)
                        <x-mockups.match-row :match="$match" />
                    @endforeach
                </section>
            @endif

            @if ($sport['clubs'] !== [] || $sport['ranges'] !== [])
                <section class="mk-section">
                    <h2>Where to shoot</h2>
                    @foreach ($sport['clubs'] as $club)
                        <a class="mk-row" href="{{ $mk('mockups.club', ['slug' => $club['slug']]) }}">
                            <span class="mk-row-title">{{ $club['name'] }}</span>
                            <span class="mk-row-meta">{{ collect(['Club', $club['place']])->filter()->implode(' · ') }}</span>
                        </a>
                    @endforeach
                    @foreach ($sport['ranges'] as $range)
                        <a class="mk-row" href="{{ $mk('mockups.range', ['slug' => $range['slug']]) }}">
                            <span class="mk-row-title">{{ $range['name'] }}</span>
                            <span class="mk-row-meta">{{ collect(['Range', $range['place']])->filter()->implode(' · ') }}</span>
                        </a>
                    @endforeach
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
                <section class="mk-section">
                    <h2>Learn about {{ $sport['name'] }}</h2>
                    @foreach ($learn as [$heading, $copy])
                        <h3 class="mk-kicker">{{ $heading }}</h3>
                        <div class="mk-prose"><p>{{ $copy }}</p></div>
                    @endforeach
                    @if ($sport['federation'])
                        <h3 class="mk-kicker">Governing organisation</h3>
                        <p><a href="{{ $mk('mockups.club', ['slug' => $sport['federation']['slug']]) }}">{{ $sport['federation']['name'] }}</a></p>
                    @endif
                    @if ($sport['related'] !== [])
                        <h3 class="mk-kicker">Related sports</h3>
                        <div class="mk-meta">
                            @foreach ($sport['related'] as $related)
                                <a href="{{ $mk('mockups.sport', ['slug' => $related['slug']]) }}">{{ $related['name'] }}</a>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endif
        @endif
    </div>
</x-mockups.layout>
