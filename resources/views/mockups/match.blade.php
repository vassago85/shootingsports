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
                <p class="mk-event-date">{{ trim($match['day'].' '.$match['month']) }}@if ($match['date']) {{ \Illuminate\Support\Carbon::parse($match['date'])->format('Y') }}@endif</p>
                @if ($match['organiser'] || $match['range'] || $match['town'])
                    <p class="mk-place">
                        @if ($match['organiser_slug'])
                            <a href="{{ $mk('mockups.club', ['slug' => $match['organiser_slug']]) }}">{{ $match['organiser'] }}</a>
                        @elseif ($match['organiser'])
                            {{ $match['organiser'] }}
                        @endif
                        @if ($match['range'] || $match['town'])
                            <br>{{ collect([$match['range'], collect([$match['town'], $match['province']])->filter()->implode(', ')])->filter()->implode(' · ') }}
                        @endif
                    </p>
                @endif
                <div class="mk-actions">
                    @if ($match['entry_url'])
                        <a class="btn" href="{{ $match['entry_url'] }}">Enter match</a>
                    @endif
                    @if ($match['calendar_url'] ?? null)
                        <a class="mk-textlink" href="{{ $match['calendar_url'] }}">Add to calendar</a>
                    @endif
                    <button class="mk-textlink" type="button" data-share="{{ $mk('mockups.match', ['slug' => $match['slug']]) }}">Share</button>
                </div>
                @php
                    $facts = collect([
                        $match['time'] === 'All day' ? 'All day' : ($match['time'] ?: null),
                        $match['fee'],
                        $match['member_fee'] ? 'Members '.$match['member_fee'] : null,
                        $match['rounds'] ? $match['rounds'].' rounds' : null,
                        $match['stages'] ? $match['stages'].' stages' : null,
                        $match['distance'],
                        in_array($match['level_value'], ['national', 'provincial', 'international'], true) ? $match['level'] : null,
                        $match['status_value'] === 'entries_open' ? 'Registration open' : null,
                        in_array('new-shooter-friendly', $match['flag_slugs'], true) ? 'Beginner friendly' : null,
                    ])->filter()->unique()->values();
                @endphp
                @if ($facts->isNotEmpty())
                    <div class="mk-facts">
                        @foreach ($facts as $fact)
                            <span>{{ $fact }}</span>
                        @endforeach
                    </div>
                @endif
            </header>

            @include('mockups.partials.ad-space', ['sponsors' => $sponsors, 'limit' => $sponsors->count()])

            @if ($match['description'] !== '')
                <section class="mk-section">
                    <h2>About</h2>
                    <div class="mk-prose">
                        @foreach (preg_split("/\n\s*\n/", $match['description']) as $paragraph)
                            <p>{{ $paragraph }}</p>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($match['equipment'])
                <section class="mk-section">
                    <h2>What you'll need</h2>
                    <div class="mk-prose"><p>{{ $match['equipment'] }}</p></div>
                </section>
            @endif

            @if ($match['range'] || $match['town'] || $match['address'])
                <section class="mk-section">
                    <h2>Venue</h2>
                    @if ($match['range'])
                        <p class="mk-row-title">
                            @if ($match['range_slug'])
                                <a href="{{ $mk('mockups.range', ['slug' => $match['range_slug']]) }}">{{ $match['range'] }}</a>
                            @else
                                {{ $match['range'] }}
                            @endif
                        </p>
                    @endif
                    @php $venueLine = collect([$match['address'], $match['town'], $match['province']])->filter()->implode(', '); @endphp
                    @if ($venueLine !== '')
                        <p class="mk-sub">{{ $venueLine }}</p>
                    @endif
                    @if ($match['directions'])
                        <p style="margin-top:10px"><a class="btn ghost" href="{{ $match['directions'] }}">Directions</a></p>
                    @endif
                </section>
            @endif

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
            @if ($match['fee'])<span>{{ $match['fee'] }}</span>@endif
            <a class="btn" href="{{ $match['entry_url'] }}">Enter match</a>
        </div>
    @endif
</x-mockups.layout>
