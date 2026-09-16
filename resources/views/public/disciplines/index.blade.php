<x-layouts.public
    title="Discover"
    description="Every shooting discipline on the South African register — what it is, who shoots it, and where the next match is."
>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">Find your sport</p>
                <h1>Discover</h1>
                <p>Precision rifle, IPSC, clays, benchrest, gong, PR22 — every discipline shot in South Africa, what it is, who governs it, and where the next match is.</p>
            </div>
        </section>
        <section class="block">
            <div class="wrap">
                {{--
                    UX audit #11: tiles are sorted by upcoming count
                    (desc) in the controller so this grid always leads
                    with disciplines that have live matches. Zero-count
                    tiles still render because the discipline page itself
                    is useful (rules, governance, history) — they just
                    swap "N upcoming" for a soft "next match — not yet
                    listed" note so the tile does not read as a dead end.
                --}}
                <div class="disc-grid">
                    @foreach ($disciplines as $discipline)
                        <a class="disc {{ $discipline->events_count === 0 ? 'is-quiet' : '' }}" href="{{ route('disciplines.show', $discipline->slug) }}">
                            <span class="fam">{{ $discipline->family->getLabel() }}</span>
                            <span class="nm">{{ $discipline->name }}</span>
                            @if ($discipline->events_count > 0)
                                <span class="ct">{{ $discipline->events_count }} upcoming</span>
                            @else
                                <span class="ct ct-quiet">No matches listed yet</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    </main>
</x-layouts.public>
