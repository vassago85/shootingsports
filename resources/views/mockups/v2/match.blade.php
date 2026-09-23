<x-mockups.v2.layout :title="data_get($match, 'title', 'Match')" active="matches">
    <div class="v2-wrap v2-page">
        @if (! $match)
            <p class="v2-empty"><strong>No match in the register yet.</strong></p>
        @else
            <header style="padding-top:24px">
                <p class="v2-kicker">{{ collect([$match['discipline'], $match['level']])->filter()->implode(' · ') }}</p>
                <h1>{{ $match['title'] }}</h1>
                <p class="v2-lede">{{ trim($match['day'].' '.$match['month']) }}@if ($match['date']) {{ \Illuminate\Support\Carbon::parse($match['date'])->format('Y') }}@endif</p>
                @if ($match['organiser'] || $match['range'] || $match['town'])
                    <p class="v2-meta">
                        @if ($match['organiser_slug'])
                            <a href="{{ $mk('mockups.v2.club', ['slug' => $match['organiser_slug']]) }}">{{ $match['organiser'] }}</a>
                        @elseif ($match['organiser'])
                            {{ $match['organiser'] }}
                        @endif
                        @if ($match['range'] || $match['town'])
                            · {{ collect([$match['range'], collect([$match['town'], $match['province']])->filter(fn (?string $part): bool => filled($part) && $part !== '—')->implode(', ')])->filter()->implode(' · ') }}
                        @endif
                    </p>
                @endif
                <div class="v2-actions">
                    @if ($match['entry_url'])<a class="v2-btn v2-btn-green" href="{{ $match['entry_url'] }}">Enter match</a>@endif
                    @if ($match['calendar_url'] ?? null)<a class="v2-btn v2-btn-line" href="{{ $match['calendar_url'] }}">Add to calendar</a>@endif
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
                    <div class="v2-facts">@foreach ($facts as $fact)<span>{{ $fact }}</span>@endforeach</div>
                @endif
            </header>
            @include('mockups.v2.partials.ad-space', ['sponsors' => $sponsors, 'limit' => $sponsors->count()])
            @if ($match['description'] !== '')
                <section class="v2-section"><h2>About</h2><div class="v2-prose">@foreach (preg_split("/\n\s*\n/", $match['description']) as $paragraph)<p>{{ $paragraph }}</p>@endforeach</div></section>
            @endif
            @if ($match['equipment'])
                <section class="v2-section"><h2>What you'll need</h2><div class="v2-prose"><p>{{ $match['equipment'] }}</p></div></section>
            @endif
            @if ($match['range'] || $match['town'] || $match['address'])
                <section class="v2-section">
                    <h2>Venue</h2>
                    @if ($match['range'])
                        <p class="v2-name">
                            @if ($match['range_slug'])<a href="{{ $mk('mockups.v2.range', ['slug' => $match['range_slug']]) }}">{{ $match['range'] }}</a>@else{{ $match['range'] }}@endif
                        </p>
                    @endif
                    @php $venueLine = collect([$match['address'], $match['town'], $match['province']])->filter(fn (?string $part): bool => filled($part) && $part !== '—')->implode(', '); @endphp
                    @if ($venueLine !== '')<p class="v2-sport">{{ $venueLine }}</p>@endif
                    @if ($match['directions'])<p style="margin-top:10px"><a class="v2-btn v2-btn-line" href="{{ $match['directions'] }}">Directions</a></p>@endif
                </section>
            @endif
            @if ($match['organiser_profile'])
                <section class="v2-section">
                    <h2>Organiser</h2>
                    <p class="v2-name"><a href="{{ $mk('mockups.v2.club', ['slug' => $match['organiser_slug']]) }}">{{ $match['organiser_profile']['name'] }}</a></p>
                    @if (filled($match['organiser_profile']['place']) && $match['organiser_profile']['place'] !== '—')<p class="v2-sport">{{ $match['organiser_profile']['place'] }}</p>@endif
                    @if ($match['organiser_profile']['description'])<p class="v2-prose">{{ \Illuminate\Support\Str::limit($match['organiser_profile']['description'], 280) }}</p>@endif
                    @if ($match['organiser_profile']['disciplines'] !== [])
                        <div class="v2-tags">@foreach (array_slice($match['organiser_profile']['disciplines'], 0, 6) as $name)<span class="v2-tag">{{ $name }}</span>@endforeach</div>
                    @endif
                </section>
            @endif
            @if ($match['more_from_organiser'] !== [])
                <section class="v2-section"><h2>Other upcoming matches from this organiser</h2><div class="v2-list" style="margin-top:12px">@foreach ($match['more_from_organiser'] as $row)<x-mockups.v2.match-row :match="$row" />@endforeach</div></section>
            @endif
            @if ($match['similar'] !== [])
                <section class="v2-section"><h2>Similar matches</h2><div class="v2-list" style="margin-top:12px">@foreach ($match['similar'] as $row)<x-mockups.v2.match-row :match="$row" />@endforeach</div></section>
            @endif
        @endif
    </div>
    @if ($match && $match['entry_url'])
        <div class="v2-sticky">
            @if ($match['fee'])<span>{{ $match['fee'] }}</span>@endif
            <a class="v2-btn v2-btn-green" href="{{ $match['entry_url'] }}">Enter match</a>
        </div>
    @endif
</x-mockups.v2.layout>
