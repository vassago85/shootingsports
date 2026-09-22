@props(['match'])
<article class="mk-match">
    <div class="mk-date">
        <span class="dow">{{ $match['dow'] }}</span>
        <b>{{ $match['day'] }}</b>
        <span class="mo">{{ $match['month'] }}</span>
    </div>
    <div>
        <h3 class="mk-title"><a href="{{ $mk('mockups.match', ['slug' => $match['slug']]) }}">{{ $match['title'] }}</a></h3>
        <div class="mk-sub">
            {{ collect([$match['discipline'], $match['organiser']])->filter()->implode(' · ') }}
        </div>
        <div class="mk-sub">
            {{ collect([$match['range'], $match['town'], $match['province']])->filter()->implode(' · ') }}
        </div>
        <div class="mk-meta">
            @if ($match['rounds'])<span>{{ $match['rounds'] }} rounds</span>@endif
            @if ($match['stages'])<span>{{ $match['stages'] }} stages</span>@endif
            @if ($match['targets'])<span>{{ $match['targets'] }} targets</span>@endif
            @if ($match['distance'])<span>{{ $match['distance'] }}</span>@endif
            @if ($match['fee'])<span>{{ $match['fee'] }}</span>@endif
            @if ($match['level'])<span>{{ $match['level'] }}</span>@endif
            @if ($match['distance_km'] !== null)<span>{{ round($match['distance_km']) }} km</span>@endif
        </div>
        @if ($match['badges'] !== [])
            <div class="mk-badges">
                @foreach ($match['badges'] as $badge)
                    <span @class(['mk-badge', $badge['tone']])>{{ $badge['label'] }}</span>
                @endforeach
            </div>
        @endif
    </div>
    <a class="btn ghost mk-go" href="{{ $mk('mockups.match', ['slug' => $match['slug']]) }}">View match</a>
</article>
