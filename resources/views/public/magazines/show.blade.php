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
            <div class="wrap" style="max-width:1100px">
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

                <noscript>
                    <p class="empty" style="margin-top:16px">
                        The flipbook needs JavaScript. You can
                        <a href="{{ $issue->pdfUrl() }}">download the PDF</a>
                        instead or scroll down to read the magazine as text.
                    </p>
                </noscript>

                <p style="margin-top:16px;display:flex;gap:12px;flex-wrap:wrap">
                    @if ($issue->pdfUrl())
                        <a class="btn ghost" href="{{ $issue->pdfUrl() }}" download>Download PDF</a>
                    @endif
                    <a class="btn ghost" href="#magazine-text">Read as text</a>
                </p>
            </div>
        </section>

        @if ($issue->pagesText() !== [])
            <section class="block" id="magazine-text">
                <div class="wrap" style="max-width:760px">
                    <div class="sec-head">
                        <p class="label">Text version</p>
                        <h2>Read as text</h2>
                        <p>Prefer another language? Open your browser menu and choose "Translate this page" — this text version is translation-ready. Each heading jumps to a page of the magazine.</p>
                    </div>

                    <article class="prose" lang="{{ $issue->language }}">
                        @foreach ($issue->pagesText() as $index => $pageText)
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
