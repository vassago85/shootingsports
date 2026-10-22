<x-layouts.public
    :title="$seo->title"
    :description="$seo->description"
    :canonical="$seo->canonical"
    :robots="$seo->robots"
    :json-ld="$jsonLd"
>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">Magazine</p>
                <h1>Magazines</h1>
                <p>Published magazines only. Each issue names its owner, so more than one publisher can host here.</p>
            </div>
        </section>

        <section class="block">
            <div class="wrap" style="max-width:960px">
                @forelse ($issues as $issue)
                    <article class="club-card" style="margin-top:16px;display:grid;grid-template-columns:120px 1fr;gap:20px;align-items:start">
                        @if ($issue->coverUrl())
                            <a href="{{ route('magazines.show', $issue->slug) }}" aria-hidden="true" tabindex="-1">
                                <img src="{{ $issue->coverUrl() }}" alt="" style="width:120px;height:auto;border-radius:6px">
                            </a>
                        @else
                            <div aria-hidden="true" style="width:120px;height:160px;background:var(--panel,#1b1b1b);border-radius:6px"></div>
                        @endif
                        <div>
                            <p class="label">{{ $issue->publication }} · {{ $issue->issue_label ?? $issue->published_at?->format('F Y') }}</p>
                            <h2><a href="{{ route('magazines.show', $issue->slug) }}">{{ $issue->title }}</a></h2>
                            @if ($issue->author?->name)
                                <p>Owner · {{ $issue->author->name }}</p>
                            @endif
                            @if ($issue->description)
                                <p>{{ $issue->description }}</p>
                            @endif
                            <p style="margin-top:12px">
                                @if ($issue->requiresLogin() && auth()->guest())
                                    <a class="btn" href="{{ route('magazines.show', $issue->slug) }}">Sign in to read</a>
                                @else
                                    <a class="btn" href="{{ route('magazines.show', $issue->slug) }}">Read this issue</a>
                                @endif
                            </p>
                        </div>
                    </article>
                @empty
                    <p class="empty">The first issue is on its way.</p>
                @endforelse
            </div>
        </section>
    </main>
</x-layouts.public>
