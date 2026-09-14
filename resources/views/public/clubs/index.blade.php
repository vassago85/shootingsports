<x-layouts.public title="Clubs" description="Clubs and associations on the South African shooting sports register, listed by home province.">
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">Directory</p>
                <h1>Clubs</h1>
                <p>A club is not bound to a venue. Home province is for orientation. The range lives on the match.</p>
            </div>
        </section>
        <section class="block">
            <div class="wrap">
                <div class="filters" role="navigation" aria-label="Filter by province">
                    <a href="{{ route('clubs.index') }}" class="{{ $province === null ? 'on' : '' }}">All</a>
                    @foreach ($provinces as $item)
                        <a href="{{ route('clubs.index', ['province' => $item->urlSlug()]) }}" class="{{ $province === $item ? 'on' : '' }}">{{ $item->getLabel() }}</a>
                    @endforeach
                </div>
                @forelse ($clubs as $club)
                    <a class="listing" href="{{ route('clubs.show', $club->slug) }}">
                        <div class="listing-row">
                            @if ($club->logoUrl())
                                <img class="listing-logo" src="{{ $club->logoUrl() }}" alt="" width="48" height="48">
                            @endif
                            <div>
                                <h3>{{ $club->name }}</h3>
                                <p class="meta">{{ $club->type->getLabel() }} · {{ $club->province?->getLabel() }}@if ($club->town) · {{ $club->town }}@endif</p>
                                <x-verification-badge :listing="$club" />
                            </div>
                        </div>
                    </a>
                @empty
                    <p class="empty">No clubs listed in this province yet.</p>
                @endforelse
            </div>
        </section>
    </main>
</x-layouts.public>
