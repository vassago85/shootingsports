<div>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">Account</p>
                <h1>My library</h1>
                <p>Issues you saved, in one place. Open one to read it.</p>
            </div>
        </section>

        <section class="block">
            <div class="wrap" style="max-width:820px">
                @if ($issues->isEmpty())
                    <div class="empty" data-test="library-empty">
                        <p>Nothing saved yet. Open a magazine and save it.</p>
                        <p class="library-actions"><a class="btn" href="{{ route('magazines.index') }}">Browse magazines</a></p>
                    </div>
                @else
                    <div data-test="library-list">
                        @foreach ($issues as $issue)
                            <article class="library-row" data-test="library-row">
                                @if ($issue->coverUrl())
                                    <a href="{{ route('magazines.show', $issue->slug) }}" aria-hidden="true" tabindex="-1">
                                        <img src="{{ $issue->coverUrl() }}" alt="">
                                    </a>
                                @else
                                    <div class="library-cover" aria-hidden="true"></div>
                                @endif
                                <div>
                                    <p class="label">{{ $issue->publication }} · {{ $issue->issue_label ?? $issue->published_at?->format('F Y') }}</p>
                                    <h2><a href="{{ route('magazines.show', $issue->slug) }}">{{ $issue->title }}</a></h2>
                                    @if ($issue->author?->name)
                                        <p>Owner · {{ $issue->author->name }}</p>
                                    @endif
                                    <div class="library-actions">
                                        <a class="btn" href="{{ route('magazines.show', $issue->slug) }}">Read this issue</a>
                                        <button
                                            type="button"
                                            class="btn ghost"
                                            wire:click="remove({{ $issue->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="remove({{ $issue->id }})"
                                        >
                                            <span wire:loading.remove wire:target="remove({{ $issue->id }})">Remove from my library</span>
                                            <span wire:loading wire:target="remove({{ $issue->id }})">Removing…</span>
                                        </button>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    </main>
</div>
