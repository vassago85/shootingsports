<x-mockups.layout title="{{ data_get($match, 'title', 'Match') }}" active="matches" :sample="data_get($match, 'title')">
    <div class="wrap" style="padding-bottom:72px">
        @if (! $match)
            <div class="mk-empty"><strong>No match in the register yet.</strong></div>
        @else
            @if ($choices->count() > 1)
                <p class="mk-support" style="padding-top:16px">
                    Other samples:
                    @foreach ($choices->take(6) as $choice)
                        <a class="mk-textlink" href="{{ $mk('mockups.match', ['slug' => $choice['slug']]) }}">{{ $choice['title'] }}</a>
                    @endforeach
                </p>
            @endif
            <header class="mk-pagehead">
                <p class="label">{{ collect([$match['discipline'], $match['level']])->filter()->implode(' · ') }}</p>
                <h1>{{ $match['title'] }}</h1>
                <p class="mk-lede">{{ collect([$match['date_label'], $match['time'] ? $match['time'] : null, $match['range'], $match['town'], $match['province']])->filter()->implode(' · ') }}</p>
                <div class="mk-actions">
                    @if ($match['entry_url'])
                        <a class="btn" href="{{ $match['entry_url'] }}">Enter match</a>
                    @else
                        <button class="btn" type="button" disabled>Entry link not listed</button>
                    @endif
                    @if ($match['calendar_url'] ?? null)
                    <a class="btn ghost" href="{{ $match['calendar_url'] }}">Add to calendar</a>
                @endif
                    <button class="btn ghost" type="button" data-share="{{ $mk('mockups.match', ['slug' => $match['slug']]) }}">Share</button>
                    @if ($match['organiser'])
                        <button class="btn ghost" type="button" data-follow="Follow organiser">Follow organiser</button>
                    @endif
                </div>
                @if ($match['badges'] !== [])
                    <div class="mk-badges">
                        @foreach ($match['badges'] as $badge)
                            <span @class(['mk-badge', $badge['tone']])>{{ $badge['label'] }}</span>
                        @endforeach
                    </div>
                @endif
            </header>

            @include('mockups.partials.ad-space', ['sponsors' => $sponsors, 'limit' => 1])

            <section class="mk-section">
                <h2>Match information</h2>
                <dl class="mk-dl">
                    <dt>Date</dt><dd>{{ $match['date_label'] ?: 'Not listed' }}</dd>
                    <dt>Start time</dt><dd>{{ $match['time'] ?: 'Not listed' }}</dd>
                    <dt>Registration closes</dt><dd>Not recorded</dd>
                    <dt>Entry fee</dt><dd>{{ $match['fee'] ?: 'Not listed' }}@if($match['member_fee']) <span class="mk-sub">(members {{ $match['member_fee'] }})</span>@endif</dd>
                    <dt>Rounds</dt><dd>{{ $match['rounds'] ?: 'Not listed' }}</dd>
                    <dt>Stages</dt><dd>{{ $match['stages'] ?: 'Not listed' }}</dd>
                    <dt>Maximum distance</dt><dd>{{ $match['distance'] ?: 'Not listed' }}</dd>
                    <dt>Match level</dt><dd>{{ $match['level'] ?: 'Not listed' }}</dd>
                    <dt>Beginner friendly</dt><dd>{{ in_array('new-shooter-friendly', $match['flag_slugs'], true) ? 'Yes' : 'Not recorded' }}</dd>
                    <dt>Organiser</dt><dd>@if($match['organiser_slug'])<a href="{{ $mk('mockups.club', ['slug' => $match['organiser_slug']]) }}">{{ $match['organiser'] }}</a>@else Not listed @endif</dd>
                    <dt>Status</dt><dd>{{ $match['status'] }}</dd>
                </dl>
            </section>

            <section class="mk-section">
                <h2>About this match</h2>
                <div class="mk-prose">
                    @if ($match['description'] !== '')
                        @foreach (preg_split("/\n\s*\n/", $match['description']) as $paragraph)
                            <p>{{ $paragraph }}</p>
                        @endforeach
                    @else
                        <p>No description has been published for this match.</p>
                    @endif
                </div>
            </section>

            @if ($match['equipment'])
                <section class="mk-section">
                    <h2>What you'll need</h2>
                    <div class="mk-prose"><p>{{ $match['equipment'] }}</p></div>
                </section>
            @endif

            <section class="mk-section">
                <h2>Venue</h2>
                <p><strong>{{ $match['range'] ?: 'Range not linked' }}</strong></p>
                <p class="mk-sub">{{ collect([$match['address'], $match['town'], $match['province']])->filter()->implode(', ') }}</p>
                @if ($match['directions'])
                    <p style="margin-top:10px"><a class="btn ghost" href="{{ $match['directions'] }}">Directions</a></p>
                @endif
                @if ($match['has_gps'])
                    <p class="mk-sub" style="margin-top:8px">{{ $match['lat'] }}, {{ $match['lng'] }}</p>
                @endif
            </section>

            @if ($match['organiser_profile'])
                <section class="mk-section">
                    <h2>Organiser</h2>
                    <p class="mk-row-title"><a href="{{ $mk('mockups.club', ['slug' => $match['organiser_slug']]) }}">{{ $match['organiser_profile']['name'] }}</a></p>
                    <p class="mk-sub">{{ $match['organiser_profile']['place'] }}</p>
                    @if ($match['organiser_profile']['description'])
                        <p style="max-width:68ch">{{ \Illuminate\Support\Str::limit($match['organiser_profile']['description'], 280) }}</p>
                    @endif
                    <p class="mk-meta">
                        @foreach (array_slice($match['organiser_profile']['disciplines'], 0, 6) as $name)
                            <span>{{ $name }}</span>
                        @endforeach
                    </p>
                </section>
            @endif

            @if ($match['more_from_organiser'] !== [])
                <section class="mk-section">
                    <h2>Other upcoming matches from this organiser</h2>
                    @foreach ($match['more_from_organiser'] as $row)
                        <x-mockups.match-row :match="$row" />
                    @endforeach
                </section>
            @endif

            @if ($match['similar'] !== [])
                <section class="mk-section">
                    <h2>Similar matches</h2>
                    @foreach ($match['similar'] as $row)
                        <x-mockups.match-row :match="$row" />
                    @endforeach
                </section>
            @endif
        @endif
    </div>
    @if ($match && $match['entry_url'])
        <div class="mk-sticky">
            <a class="btn" href="{{ $match['entry_url'] }}">Enter match</a>
        </div>
    @endif
</x-mockups.layout>
