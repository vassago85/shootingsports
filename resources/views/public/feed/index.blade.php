<x-layouts.public title="Activity" description="Articles from media partners, tagged to sports and event types.">
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">Activity</p>
                <h1>What is happening</h1>
            </div>
        </section>
        <section class="block">
            <div class="wrap" style="max-width:760px">
                <form method="get" action="{{ route('feed') }}" class="my-log-filters">
                    <label class="field">
                        <span>Sport</span>
                        <select name="discipline" onchange="this.form.submit()">
                            <option value="">All sports</option>
                            @foreach ($disciplines as $item)
                                <option value="{{ $item->slug }}" @selected($discipline?->is($item))>{{ $item->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field">
                        <span>Event type</span>
                        <select name="kind" onchange="this.form.submit()">
                            <option value="">All types</option>
                            @foreach ($kinds as $item)
                                <option value="{{ $item->value }}" @selected($kind === $item)>{{ $item->getLabel() }}</option>
                            @endforeach
                        </select>
                    </label>
                </form>
                @forelse ($articles as $article)
                    <article class="club-card" style="margin-top:16px">
                        <p class="label">{{ $article->published_at?->format('j M Y') }} · {{ $article->author?->name }}</p>
                        <h2>{{ $article->title }}</h2>
                        @if ($article->excerpt)
                            <p>{{ $article->excerpt }}</p>
                        @endif
                        <div class="prose">{!! nl2br(e($article->body)) !!}</div>
                    </article>
                @empty
                    <p class="empty">No published articles yet.</p>
                @endforelse
            </div>
        </section>
    </main>
</x-layouts.public>
