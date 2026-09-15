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
                <div class="disc-grid">
                    @foreach ($disciplines as $discipline)
                        <a class="disc" href="{{ route('disciplines.show', $discipline->slug) }}">
                            <span class="fam">{{ $discipline->family->getLabel() }}</span>
                            <span class="nm">{{ $discipline->name }}</span>
                            <span class="ct">{{ $discipline->events_count }} upcoming</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    </main>
</x-layouts.public>
