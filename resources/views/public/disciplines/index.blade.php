<x-layouts.public
    title="Shooting sports disciplines"
    description="Every shooting discipline on the South African register — what it is, who shoots it, and where the next match is."
    :json-ld="$jsonLd"
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
                {{-- Bundle A #2: populated tiles first; empties behind
                     "Show all N disciplines →" so the page doesn't open
                     as a wall of "No matches listed yet". --}}
                @php
                    $populated = $disciplines->filter(fn ($d) => ($d->events_count ?? 0) > 0);
                    $empty = $disciplines->filter(fn ($d) => ($d->events_count ?? 0) === 0);
                    $total = $disciplines->count();
                @endphp
                <div class="disc-grid">
                    @foreach ($populated as $discipline)
                        <a class="disc" href="{{ route('disciplines.show', $discipline->slug) }}">
                            <span class="fam">{{ $discipline->family->getLabel() }}</span>
                            <span class="nm">{{ $discipline->name }}</span>
                            <span class="ct">{{ $discipline->events_count }} upcoming</span>
                        </a>
                    @endforeach
                </div>
                @if ($empty->isNotEmpty())
                    <details class="disc-more">
                        <summary>Show all {{ $total }} disciplines →</summary>
                        <div class="disc-grid" style="margin-top:1px">
                            @foreach ($empty as $discipline)
                                <a class="disc is-quiet" href="{{ route('disciplines.show', $discipline->slug) }}">
                                    <span class="fam">{{ $discipline->family->getLabel() }}</span>
                                    <span class="nm">{{ $discipline->name }}</span>
                                    <span class="ct ct-quiet">No matches listed yet</span>
                                </a>
                            @endforeach
                        </div>
                    </details>
                @endif
            </div>
        </section>
    </main>
</x-layouts.public>
