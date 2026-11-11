<x-layouts.public
    :title="$seo->title"
    :description="$seo->description"
    :canonical="$seo->canonical"
    :json-ld="$jsonLd"
>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label"><a href="{{ route('disciplines.index') }}">Discover</a></p>
                <h1>Event type glossary</h1>
                <p>Every event type on the register, with the other names it goes by. Clubs and associations are listed separately, under <a href="{{ route('clubs.index') }}">Clubs &amp; associations</a>.</p>
            </div>
        </section>

        <section class="block">
            <div class="wrap" style="max-width:800px">
                @foreach ($disciplines as $discipline)
                    <article id="{{ $discipline->slug }}" style="padding:22px 0;border-top:1px solid var(--line, rgba(255,255,255,.12))">
                        <h2><a href="{{ route('disciplines.show', $discipline->slug) }}">{{ $discipline->name }}</a></h2>
                        @if (filled($discipline->keywords))
                            <p class="label">Also called {{ $discipline->keywords }}</p>
                        @endif
                        @if ($discipline->glossaryDescription())
                            <p>{{ $discipline->glossaryDescription() }}</p>
                        @else
                            <p class="empty">No description yet.</p>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    </main>
</x-layouts.public>
