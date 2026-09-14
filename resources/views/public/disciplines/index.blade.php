<x-layouts.public
    title="Disciplines"
    description="Every shooting discipline on the South African register — what it is, who shoots it, and where the next match is."
>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">The grid</p>
                <h1>Disciplines</h1>
                <p>Each page is a real destination. Discipline × province pages exist for search. Thin combinations are noindexed, not deleted.</p>
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
