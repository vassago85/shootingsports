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
                    @if (($sport['children'] ?? []) !== [])
                        <a class="btn ghost" href="{{ $mk('mockups.matches', ['sport' => $sport['slug']]) }}">All matches</a>
                        <a class="btn ghost" href="{{ $mk('mockups.matches.calendar', ['sport' => $sport['slug']]) }}">Calendar</a>
                    @else
                        <a class="btn" href="{{ $mk('mockups.matches', ['sport' => $sport['slug']]) }}">Match list</a>
                        <a class="btn ghost" href="{{ $mk('mockups.matches.calendar', ['sport' => $sport['slug']]) }}">Calendar</a>
                    @endif
                    <a class="btn ghost" href="{{ $mk('mockups.clubs', ['sport' => $sport['slug']]) }}">Find a club</a>
                </div>
            </header>

            @php
                $sportSlugs = array_values(array_unique(array_filter([
                    $sport['slug'],
                    ...array_column($sport['children'] ?? [], 'slug'),
                ])));
                $categorySponsors = collect($sponsors ?? [])->filter(
                    fn (array $sponsor): bool => array_intersect($sponsor['discipline_slugs'] ?? [], $sportSlugs) !== []
                )->values();
            @endphp
            @include('mockups.partials.ad-space', ['sponsors' => $categorySponsors, 'limit' => 1])

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

            <section class="mk-section">
                <h2>What is {{ $sport['name'] }}?</h2>
                <div class="mk-prose">
                    @if ($sport['paragraphs'] !== [])
                        <p>{{ $sport['paragraphs'][0] }}</p>
                    @elseif ($sport['blurb'])
                        <p>{{ $sport['blurb'] }}</p>
                    @else
                        <p>A longer explanation has not been written for this sport yet.</p>
                    @endif
                </div>
            </section>

            <section class="mk-section">
                <h2>What happens at a match?</h2>
                <div class="mk-prose">
                    @forelse (array_slice($sport['paragraphs'], 1) as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @empty
                        <p>The register does not yet describe a typical match for {{ $sport['name'] }} beyond the short introduction.</p>
                    @endforelse
                </div>
            </section>

            <section class="mk-section">
                <h2>What equipment do I need?</h2>
                <div class="mk-prose">
                    @if ($sport['equipment'])
                        <p>{{ $sport['equipment'] }}</p>
                    @else
                        <p>Equipment rules have not been written for this sport yet.@if($sport['distances']) Typical distances on the register: {{ $sport['distances'] }}.@endif</p>
                    @endif
                </div>
            </section>

            <section class="mk-section">
                <h2>Can beginners participate?</h2>
                <div class="mk-prose">
                    @if ($sport['beginner_paragraph'])
                        <p>{{ $sport['beginner_paragraph'] }}</p>
                    @else
                        <p>There is no beginner note on this sport page yet. Individual matches can still be flagged new-shooter friendly.</p>
                    @endif
                </div>
            </section>

            <section class="mk-section">
                <h2>Typical costs</h2>
                @if ($sport['fees'] !== [])
                    <p>Published entry fees on upcoming {{ $sport['name'] }} matches: {{ implode(', ', $sport['fees']) }}.</p>
                @else
                    <p>No entry fees are published on upcoming matches for this sport, so a typical cost is not shown.</p>
                @endif
            </section>

            <section class="mk-section">
                <h2>Governing organisations</h2>
                @if ($sport['federation'])
                    <p><a href="{{ $mk('mockups.club', ['slug' => $sport['federation']['slug']]) }}">{{ $sport['federation']['name'] }}</a></p>
                @else
                    <p>No governing body is linked to this sport yet.</p>
                @endif
            </section>

            <section class="mk-section">
                <h2>Upcoming matches</h2>
                @forelse ($sport['upcoming'] as $match)
                    <x-mockups.match-row :match="$match" />
                @empty
                    <p>No upcoming matches are listed for {{ $sport['name'] }}.</p>
                @endforelse
            </section>

            <section class="mk-section">
                <h2>Clubs offering this sport</h2>
                @forelse ($sport['clubs'] as $club)
                    <a class="mk-row" href="{{ $mk('mockups.club', ['slug' => $club['slug']]) }}">
                        <span class="mk-row-title">{{ $club['name'] }}</span>
                        <span class="mk-row-meta">{{ $club['place'] }}</span>
                    </a>
                @empty
                    <p>No clubs are linked to {{ $sport['name'] }} yet.</p>
                @endforelse
            </section>

            <section class="mk-section">
                <h2>Ranges where it is shot</h2>
                @forelse ($sport['ranges'] as $range)
                    <a class="mk-row" href="{{ $mk('mockups.range', ['slug' => $range['slug']]) }}">
                        <span class="mk-row-title">{{ $range['name'] }}</span>
                        <span class="mk-row-meta">{{ $range['place'] }}</span>
                    </a>
                @empty
                    <p>No ranges are linked to {{ $sport['name'] }} yet.</p>
                @endforelse
            </section>

            @if ($sport['related'] !== [])
                <section class="mk-section">
                    <h2>Related sports</h2>
                    <div class="mk-tiles">
                        @foreach ($sport['related'] as $related)
                            <a class="mk-tile" href="{{ $mk('mockups.sport', ['slug' => $related['slug']]) }}">
                                <span class="fam">{{ $related['family_label'] }}</span>
                                <strong>{{ $related['name'] }}</strong>
                                <span>{{ $related['blurb'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        @endif
    </div>
</x-mockups.layout>
