<div class="app-pad">
    <div class="app-home-head">
        <div class="app-home-title">
            <span>{{ $home['place'] ?? ($close['place'] ?? 'South Africa') }}</span>
            <strong>Your shooting</strong>
        </div>
        <a class="app-iconbtn" href="{{ $app('alerts') }}" aria-label="Alerts">
            <x-mockups.icon name="bell" />
            <span class="app-dot" aria-hidden="true"></span>
        </a>
        <a class="app-avatar" href="{{ $app('you') }}" aria-label="You">{{ $home['initials'] ?? 'P' }}</a>
    </div>

    @if ($home['featured'] ?? null)
        @php $featured = $home['featured']; @endphp
        <p class="app-sub" style="margin-top: 4px;">Next up</p>
        <a class="app-featured" href="{{ $app('match', ['match' => $featured['slug']]) }}">
            <span class="app-eyebrow">{{ $featured['dow'] }} {{ $featured['day'] }} {{ $featured['month'] }}</span>
            <h2 class="app-featured-title">{{ $featured['title'] }}</h2>
            <p class="app-featured-meta">{{ collect([$featured['discipline'], $featured['range'] ?: $featured['town'], $featured['town'] ? null : $featured['province']])->filter()->implode(' · ') }}</p>
            <div class="app-featured-strip">
                @if ($featured['town'] || $featured['province'])
                    <span>{{ collect([$featured['town'], $kmAway($featured)])->filter()->implode(' · ') }}</span>
                @endif
                @if ($featured['distance'])<span><b>{{ $featured['distance'] }}</b></span>@endif
            </div>
            <span class="app-featured-btn">View match</span>
        </a>
    @endif

    @if (($home['coming'] ?? collect())->isNotEmpty())
        <p class="app-sub">Coming up</p>
        @foreach ($home['coming'] as $row)
            <a class="app-match" href="{{ $app('match', ['match' => $row['slug']]) }}">
                <span class="app-when"><b>{{ $row['day'] }}</b><span>{{ $row['month'] }}</span></span>
                <span>
                    <strong>{{ $row['title'] }}</strong>
                    <em>{{ collect([$row['discipline'], $row['town'] ?: $row['province'], $kmAway($row)])->filter()->implode(' · ') }}</em>
                </span>
            </a>
        @endforeach
        <a class="app-see-all" href="{{ $app('matches') }}">See all matches <x-mockups.icon name="chevron" /></a>
    @endif

    @include('mockups.partials.app-banners', ['banners' => $bannersFor('home')->take(1)])

    @if (($home['nearby'] ?? collect())->isNotEmpty())
        <p class="app-sub">Near you</p>
        @foreach ($home['nearby'] as $row)
            <a class="app-match" href="{{ $app('match', ['match' => $row['slug']]) }}">
                <span class="app-when"><b>{{ $row['day'] }}</b><span>{{ $row['month'] }}</span></span>
                <span>
                    <strong>{{ $row['title'] }}</strong>
                    <em>{{ collect([$row['town'] ?: $row['province'], $kmAway($row)])->filter()->implode(' · ') }}</em>
                </span>
            </a>
        @endforeach
    @endif

    @if (($home['activity'] ?? []) !== [])
        <p class="app-sub">From clubs you follow</p>
        <ul class="app-activity">
            @foreach ($home['activity'] as $item)
                <li>
                    <x-mockups.icon name="flag" />
                    <span><strong>{{ $item['text'] }}</strong><em>{{ $item['when'] }}</em></span>
                </li>
            @endforeach
        </ul>
    @endif
</div>
