<x-layouts.public :title="$seoTitle" :description="$seoDescription" :json-ld="$jsonLd">
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">Directory</p>
                <h1>Ranges</h1>
                <p>Venues are independent of clubs. A match names the range it is shot on.</p>
            </div>
        </section>
        <section class="block">
            <div class="wrap">
                <div class="filters">
                    <a href="{{ route('ranges.index', array_filter(['division' => $division?->value])) }}" class="{{ $province === null ? 'on' : '' }}">All</a>
                    @foreach ($provinces as $item)
                        <a href="{{ route('ranges.index', array_filter(['province' => $item->urlSlug(), 'division' => $division?->value])) }}" class="{{ $province === $item ? 'on' : '' }}">{{ $item->getLabel() }}</a>
                    @endforeach
                </div>
                {{-- UX audit #13: ad-slot below the filters + the first
                     row of results, and hide-when-vacant so unsold
                     inventory does not render a house pitch above the
                     actual directory. --}}
                @forelse ($venues as $index => $venue)
                    <a class="listing" href="{{ route('ranges.show', $venue->slug) }}">
                        <h3>{{ $venue->name }} <x-listing-tier-badge :listing="$venue" /></h3>
                        <p class="meta">
                            {{ $venue->town }} · {{ $venue->province?->getLabel() }}
                            @if ($venue->max_distance_m)
                                · {{ number_format($venue->max_distance_m) }} m
                            @endif
                        </p>
                        <x-verification-badge :listing="$venue" />
                    </a>
                    @if ($index === 2)
                        <x-ad-slot page="ranges" placement-slot="in_feed_native" :limit="2" class="ss-partner--tight" hide-when-vacant />
                    @endif
                @empty
                    <p class="empty">No ranges listed in this province yet.</p>
                @endforelse
            </div>
        </section>
    </main>
</x-layouts.public>
