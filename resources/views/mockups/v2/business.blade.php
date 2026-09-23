<x-mockups.v2.layout :title="data_get($business, 'name', 'Business')" active="industry">
    <div class="v2-wrap v2-page">
        @if (! $business)
            <p class="v2-empty"><strong>No industry listings are published yet.</strong></p>
        @elseif (! $business['public'])
            <header style="padding-top:28px">
                <p class="v2-kicker">Advertising account</p>
                <h1>{{ $business['name'] }}</h1>
                <p class="v2-lede">Distributors are not on the public register. This account is for advertising.</p>
                @if ($business['website'])<div class="v2-actions"><a class="v2-btn v2-btn-green" href="{{ $business['website'] }}">Website</a></div>@endif
            </header>
        @else
            <header style="padding-top:28px">
                @if (filled($business['category']))<p class="v2-kicker">{{ $business['category'] }}</p>@endif
                <h1>{{ $business['name'] }}</h1>
                @if (filled($business['place']) && $business['place'] !== '—')<p class="v2-lede">{{ $business['place'] }}</p>@endif
                <div class="v2-tags" style="margin-top:10px">
                    @if ($business['verified'])<span class="v2-badge">Verified</span>@endif
                    @if ($business['featured'])<span class="v2-badge sponsored">Sponsored</span>@endif
                </div>
                <div class="v2-actions">
                    @if ($business['website'])<a class="v2-btn v2-btn-green" href="{{ $business['website'] }}">Website</a>@endif
                    @if ($business['email'])<a class="v2-btn v2-btn-line" href="mailto:{{ $business['email'] }}">Email</a>@endif
                    @if ($business['phone'])<a class="v2-btn v2-btn-line" href="tel:{{ $business['phone'] }}">Call</a>@endif
                </div>
            </header>
            @if ($business['description'])<section class="v2-section"><h2>About</h2><div class="v2-prose"><p>{{ $business['description'] }}</p></div></section>@endif
            @if ($business['services'] !== [])<section class="v2-section"><h2>Services</h2><div class="v2-tags">@foreach ($business['services'] as $service)<span class="v2-tag">{{ $service }}</span>@endforeach</div></section>@endif
            @if ($business['disciplines'] !== [])<section class="v2-section"><h2>Disciplines</h2><div class="v2-tags">@foreach ($business['disciplines'] as $name)<span class="v2-tag">{{ $name }}</span>@endforeach</div></section>@endif
            @if ($business['placements'] !== [])
                <section class="v2-section">
                    <h2>Supporting shooting sports</h2>
                    <ul class="v2-activity">
                        @foreach ($business['placements'] as $placement)
                            <li><time>Ad</time><span>{{ $placement['name'] }}@if($placement['slot']) · {{ $placement['slot'] }}@endif</span></li>
                        @endforeach
                    </ul>
                </section>
            @endif
        @endif
    </div>
</x-mockups.v2.layout>
