<x-layouts.public
    title="Shooting sports disciplines"
    description="Every shooting discipline on the South African register. What it is, who shoots it, and where the next match is."
    :json-ld="$jsonLd"
>
    <main id="main">
        <div class="wrap dir-page">
            <x-dir-hero page="sports" kicker="Find your sport" title="Sports">
                Precision rifle, IPSC, clays, benchrest, gong, PR22. Every discipline shot in South Africa, what it is, who governs it, and where the next match is.
            </x-dir-hero>
        </div>
        <section class="block">
            <div class="wrap">
                {{-- Two named sections: "Active now" is where a
                     shooter can see a match on the calendar in the
                     next stretch; "Explore all shooting sports" is
                     the reference view for the rest. Same split
                     rule as before, clearer labels. --}}
                @php
                    $active = $disciplines->filter(fn ($d) => ($d->events_count ?? 0) > 0);
                    $reference = $disciplines->filter(fn ($d) => ($d->events_count ?? 0) === 0);
                    $total = $disciplines->count();
                @endphp

                @if ($active->isNotEmpty())
                    <div class="sec-head">
                        <p class="label">On the calendar</p>
                        <h2>Active now</h2>
                    </div>
                    <div class="disc-grid">
                        @foreach ($active as $discipline)
                            <a @class(['disc', 'has-photo' => filled($discipline->imageUrl())]) href="{{ route('disciplines.show', $discipline->slug) }}" @if ($discipline->imageUrl()) style="--hero-image: url('{{ str_replace(['\\', "'"], ['/', ''], $discipline->imageUrl()) }}')" @endif>
                                <span class="fam">{{ $discipline->family->getLabel() }}</span>
                                <span class="nm">{{ $discipline->name }}</span>
                                <span class="ct">{{ $discipline->events_count }} upcoming</span>
                            </a>
                        @endforeach
                    </div>
                @endif

                @if ($reference->isNotEmpty())
                    <div class="sec-head" @if ($active->isNotEmpty()) style="margin-top:36px" @endif>
                        <p class="label">Reference</p>
                        <h2>Explore all shooting sports</h2>
                    </div>
                    {{-- Details/summary stays even when Active now is
                         empty so shooters can browse quiet sports in
                         one place, and the "Show all" affordance keeps
                         the section discoverable. --}}
                    <details class="disc-more" @if ($active->isEmpty()) open @endif>
                        <summary>Show all {{ $total }} disciplines →</summary>
                        <div class="disc-grid" style="margin-top:1px">
                            @foreach ($reference as $discipline)
                                <a @class(['disc', 'is-quiet', 'has-photo' => filled($discipline->imageUrl())]) href="{{ route('disciplines.show', $discipline->slug) }}" @if ($discipline->imageUrl()) style="--hero-image: url('{{ str_replace(['\\', "'"], ['/', ''], $discipline->imageUrl()) }}')" @endif>
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
