<x-layouts.public
    :title="$seo->title"
    :description="$seo->description"
    :canonical="$seo->canonical"
    :robots="$seo->robots"
    :image="$issue->coverUrl()"
    :json-ld="$jsonLd"
>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">
                    <a href="{{ route('magazines.index') }}">Magazine</a>
                    · {{ $issue->publication }}
                    @if ($issue->issue_label)
                        · {{ $issue->issue_label }}
                    @endif
                </p>
                <h1>{{ $issue->title }}</h1>
                @if ($issue->author?->name)
                    <p>Owner · {{ $issue->author->name }}</p>
                @endif
                @if ($issue->description)
                    <p>{{ $issue->description }}</p>
                @endif
                @if ($preview)
                    <p class="empty" style="margin-top:14px;max-width:640px">
                        You are viewing a private preview of a draft issue. Reader tracking is paused and search engines will not index this page.
                    </p>
                @endif
            </div>
        </section>

        <section class="block">
            <div class="wrap" style="max-width:1440px">
                <div class="magazine-stage" id="magazine-stage">
                    <div
                        id="magazine-flipbook"
                        class="magazine-flipbook"
                        data-pdf-url="{{ $issue->pdfUrl() }}"
                        data-read-url="{{ route('magazines.read', $issue->slug) }}"
                        @if (! $preview && $issue->isPublished())
                            data-track="1"
                        @endif
                        aria-label="{{ $issue->title }} flipbook"
                    >
                        <p class="magazine-flipbook__loading">Loading the flipbook…</p>
                    </div>

                    <p class="magazine-stage__bar">
                        <button type="button" class="btn ghost" id="magazine-fullscreen" aria-pressed="false">Full screen</button>
                        @if ($issue->pdfUrl())
                            <a class="btn ghost" href="{{ $issue->pdfUrl() }}" download>Download PDF</a>
                        @endif
                        @if ($issue->hasReadableText())
                            <a class="btn ghost" href="#magazine-text">Read as text</a>
                        @endif
                    </p>
                </div>

                <noscript>
                    <p class="empty" style="margin-top:16px">
                        The flipbook needs JavaScript. You can
                        <a href="{{ $issue->pdfUrl() }}">download the PDF</a>
                        instead or scroll down to read the magazine as text.
                    </p>
                </noscript>
            </div>
        </section>

        @if ($issue->hasReadableText())
            <section class="block" id="magazine-text">
                <div class="wrap" style="max-width:760px">
                    <div class="sec-head">
                        <p class="label">Text version</p>
                        <h2>Read as text</h2>
                        <p>Prefer another language? Open your browser menu and choose "Translate this page" — this text version is translation-ready. Each heading jumps to a page of the magazine.</p>
                    </div>

                    <article class="prose" lang="{{ $issue->language }}">
                        @foreach ($issue->pagesText() as $index => $pageText)
                            @continue(trim($pageText) === '')
                            @php
                                $pageNumber = $index + 1;
                            @endphp
                            <section id="page-{{ $pageNumber }}" style="margin-top:28px">
                                <h3>Page {{ $pageNumber }}</h3>
                                <div>{!! nl2br(e($pageText)) !!}</div>
                            </section>
                        @endforeach
                    </article>
                </div>
            </section>
        @endif
    </main>

    @vite('resources/js/flipbook.js')
</x-layouts.public>
