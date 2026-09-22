<x-layouts.public
    :title="$division->getLabel().' shooting in South Africa'"
    :description="'Sports, clubs, ranges and matches for '.$division->getLabel().'.'"
>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">Sports</p>
                <h1>{{ $division->getLabel() }}</h1>
                <p>The sports in this division. Each one has its own page: what it is, who runs it, and videos when they have been added.</p>
            </div>
        </section>

        <div class="wrap" style="padding-top:28px">
            <x-ad-slot page="disciplines" placement-slot="category_sponsor" :division="$division" :limit="1" hide-when-vacant />
        </div>

        <section class="block" style="padding-top:12px">
            <div class="wrap">
                @if ($sports->isEmpty())
                    <p class="empty">No sports are listed in this division yet.</p>
                @else
                    <div class="division-sports">
                        @foreach ($sports as $sport)
                            <a class="division-sport" href="{{ route('disciplines.show', $sport->slug) }}">
                                <span class="nm">{{ $sport->name }}</span>
                                <span class="blurb">{{ $sport->short_blurb }}</span>
                                @if ($sport->federation)
                                    <span class="org">{{ $sport->federation->short_name ?: $sport->federation->name }}</span>
                                @endif
                                <span class="moreline">The full page →</span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        <section class="block">
            <div class="wrap">
                <p class="label">Clubs</p>
                <h2>Clubs &amp; series</h2>
                @forelse ($clubs as $club)
                    <a class="listing" href="{{ route('clubs.show', $club->slug) }}">
                        <h3>{{ $club->name }}</h3>
                        <p class="meta">{{ $club->province?->getLabel() }}</p>
                    </a>
                @empty
                    <p class="empty">No clubs listed in this division yet.</p>
                @endforelse
                <p class="moreline"><a href="{{ route('clubs.index', ['division' => $division->value]) }}">All {{ $division->getLabel() }} clubs →</a></p>
            </div>
        </section>

        <section class="block">
            <div class="wrap">
                <p class="label">Ranges</p>
                <h2>Ranges</h2>
                @forelse ($ranges as $range)
                    <a class="listing" href="{{ route('ranges.show', $range->slug) }}">
                        <h3>{{ $range->name }}</h3>
                        <p class="meta">{{ $range->province?->getLabel() }}</p>
                    </a>
                @empty
                    <p class="empty">No ranges listed in this division yet.</p>
                @endforelse
                <p class="moreline"><a href="{{ route('ranges.index', ['division' => $division->value]) }}">All {{ $division->getLabel() }} ranges →</a></p>
            </div>
        </section>

        <section class="block">
            <div class="wrap">
                <p class="label">Matches</p>
                <h2>Upcoming matches</h2>
                @forelse ($matches as $match)
                    <a class="listing" href="{{ route('matches.show', $match->slug) }}">
                        <h3>{{ $match->title }}</h3>
                    </a>
                @empty
                    <p class="empty">No upcoming matches in this division yet.</p>
                @endforelse
                <p class="moreline"><a href="{{ route('calendar', ['family' => $division->value]) }}">All {{ $division->getLabel() }} matches →</a></p>
            </div>
        </section>

        <section class="block">
            <div class="wrap">
                <p class="label">Industry</p>
                <h2>Suppliers</h2>
                @forelse ($suppliers as $supplier)
                    <a class="listing" href="{{ route('suppliers.show', $supplier->slug) }}">
                        <h3>{{ $supplier->name }}</h3>
                    </a>
                @empty
                    <p class="empty">No suppliers listed in this division yet.</p>
                @endforelse
                <p class="moreline"><a href="{{ route('suppliers.index', ['division' => $division->value]) }}">All {{ $division->getLabel() }} suppliers →</a></p>
            </div>
        </section>
    </main>
</x-layouts.public>
