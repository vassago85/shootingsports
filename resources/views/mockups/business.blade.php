<x-mockups.layout title="{{ data_get($business, 'name', 'Business') }}" active="industry" :sample="data_get($business, 'name')">
    <div class="wrap" style="padding-bottom:48px">
        @if (! $business)
            <div class="mk-empty"><strong>No industry listings are published yet.</strong></div>
        @elseif (! $business['public'])
            <header class="mk-pagehead">
                <p class="label">Advertising account</p>
                <h1>{{ $business['name'] }}</h1>
                <p class="mk-lede">Distributors are not on the public register. This account is for advertising.</p>
                @if ($business['website'])
                    <div class="mk-actions"><a class="btn" href="{{ $business['website'] }}">Website</a></div>
                @endif
            </header>
        @else
            <header class="mk-pagehead">
                <p class="label">{{ $business['category'] }}</p>
                <h1>{{ $business['name'] }}</h1>
                <p class="mk-lede">{{ $business['place'] }}</p>
                <div class="mk-badges">
                    @if ($business['verified'])<span class="mk-badge">Verified</span>@endif
                    @if ($business['featured'])<span class="mk-badge sponsored">Sponsored</span>@endif
                </div>
                <div class="mk-actions">
                    @if ($business['website'])<a class="btn" href="{{ $business['website'] }}">Website</a>@endif
                    @if ($business['email'])<a class="btn ghost" href="mailto:{{ $business['email'] }}">Email</a>@endif
                    @if ($business['phone'])<a class="btn ghost" href="tel:{{ $business['phone'] }}">Call</a>@endif
                </div>
            </header>
            <section class="mk-section">
                <h2>About</h2>
                <div class="mk-prose"><p>{{ $business['description'] ?: 'No description has been published.' }}</p></div>
            </section>
            <section class="mk-section">
                <h2>Services</h2>
                @if ($business['services'] === [])
                    <p>Category: {{ $business['category'] ?: 'Not listed' }}. No extra services are listed.</p>
                @else
                    <div class="mk-meta">@foreach ($business['services'] as $service)<span>{{ $service }}</span>@endforeach</div>
                @endif
            </section>
            @if ($business['disciplines'] !== [])
                <section class="mk-section">
                    <h2>Disciplines</h2>
                    <p>{{ implode(', ', $business['disciplines']) }}</p>
                </section>
            @endif
            <section class="mk-section">
                <h2>Supporting shooting sports</h2>
                @if ($business['placements'] !== [])
                    <ul class="mk-activity">
                        @foreach ($business['placements'] as $placement)
                            <li><time>Ad</time><span>{{ $placement['name'] }}@if($placement['slot']) · {{ $placement['slot'] }}@endif</span></li>
                        @endforeach
                    </ul>
                @else
                    <p>No sponsored clubs or matches are linked to this business.</p>
                @endif
            </section>
        @endif
    </div>
</x-mockups.layout>
