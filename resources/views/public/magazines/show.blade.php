<x-layouts.public
    :title="$seo->title"
    :description="$seo->description"
    :canonical="$seo->canonical"
    :robots="$seo->robots"
    :image="$issue->coverUrl()"
    :json-ld="$jsonLd"
>
    <main id="main" class="magazine-show">
        <div class="magazine-meta">
            <div class="wrap">
                <p class="label">
                    <a href="{{ route('magazines.index') }}">Magazines</a>
                    · {{ $issue->publication }}
                    @if ($issue->issue_label)
                        · {{ $issue->issue_label }}
                    @endif
                </p>
                <div class="magazine-meta__row">
                    <h1>{{ $issue->title }}</h1>
                    @if ($publisher = $issue->publisherLabel())
                        <p>Published by {{ $publisher }}</p>
                    @endif
                    @if ($issue->hasReadableText())
                        <a href="#magazine-text">Read as text</a>
                    @endif
                    @if (! $preview && $issue->isPublished() && ! $issue->pdfUrl())
                        <livewire:save-magazine-issue :issue="$issue" :key="'save-issue-meta-'.$issue->id" />
                    @endif
                </div>
                @if ($issue->description)
                    <p class="magazine-meta__summary">{{ $issue->description }}</p>
                @endif
                @if ($preview)
                    <p class="empty">
                        You are viewing a private preview of a draft issue. Reader tracking is paused and search engines will not index this page.
                    </p>
                @endif
                @if ($resumePage)
                    <p class="magazine-resume">
                        <button type="button" data-magazine-jump="{{ $resumePage }}">Continue at page {{ $resumePage }}</button>
                    </p>
                @endif
            </div>
        </div>

        <div class="magazine-stage" id="magazine-stage">
            @if ($issue->pdfUrl())
                <div
                    id="magazine-flipbook"
                    class="magazine-flipbook"
                    data-pdf-url="{{ $issue->pdfUrl() }}"
                    data-read-url="{{ route('magazines.read', $issue->slug) }}"
                    data-slug="{{ $issue->slug }}"
                    data-page-count="{{ $issue->page_count ?? '' }}"
                    @if (request()->integer('page') > 0)
                        data-start-page="{{ request()->integer('page') }}"
                    @endif
                    @if ($resumePage)
                        data-resume-page="{{ $resumePage }}"
                    @endif
                    @if (auth()->check())
                        data-progress-url="{{ route('magazines.progress', $issue->slug) }}"
                    @endif
                    @if (! $preview && $issue->isPublished())
                        data-track="1"
                    @endif
                    aria-label="{{ $issue->title }} flipbook"
                >
                    <div class="magazine-skeleton">
                        @if ($issue->coverUrl())
                            <img src="{{ $issue->coverUrl() }}" alt="">
                        @endif
                        <p class="magazine-flipbook__loading">Loading the flipbook…</p>
                    </div>
                </div>
                <script type="application/json" id="magazine-hotspots">{!! \App\Support\JsonLd::encode($issue->readerHotspots()) !!}</script>
                <script type="application/json" id="magazine-page-images">{!! \App\Support\JsonLd::encode($issue->pageImageUrls()) !!}</script>

                <div class="magazine-toolbar" id="magazine-toolbar">
                    <div class="magazine-toolbar__nav" data-magazine-nav></div>
                    <div class="magazine-toolbar__tools">
                        <button type="button" class="btn ghost" id="magazine-fullscreen" aria-pressed="false" aria-label="Full screen">Full screen</button>
                        @if (! $preview && $issue->isPublished())
                            <livewire:save-magazine-issue :issue="$issue" :key="'save-issue-'.$issue->id" />
                        @endif
                        @if ($issue->allowsPdfDownload() && ! $preview)
                            <button type="button" class="btn ghost" id="magazine-download-open" aria-label="Email the PDF">Download</button>
                        @endif
                    </div>
                </div>
                <div class="magazine-thumbs" id="magazine-thumbs" hidden></div>
            @else
                <p class="empty">This issue is not on the reader yet.</p>
            @endif
        </div>

        @if ($issue->allowsPdfDownload() && ! $preview)
            <dialog class="magazine-dialog" id="magazine-download" @if (session('download_link_sent') || $errors->has('email')) open @endif>
                <form method="post" action="{{ route('magazines.download-link', $issue->slug) }}" class="enquiry-form">
                    @csrf
                    <div class="hp" aria-hidden="true">
                        <label for="company_website-{{ $issue->id }}">Company website</label>
                        <input type="text" name="company_website" id="company_website-{{ $issue->id }}" value="" tabindex="-1" autocomplete="off">
                    </div>
                    <h2>Email the PDF</h2>
                    @if (session('download_link_sent'))
                        <p>Check your email for the download link. It expires in 7 days. Reading on this page stays free.</p>
                    @else
                        <label class="field">
                            <span>Email the PDF</span>
                            <input type="email" name="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email">
                        </label>
                        @error('email')
                            <p class="empty">{{ $message }}</p>
                        @enderror
                        <button type="submit" class="btn">Send download link</button>
                        <p>Reading on this page is free. We email you a download link.</p>
                    @endif
                    <button type="button" class="btn ghost" id="magazine-download-close">Close</button>
                </form>
            </dialog>
        @endif

        @php $advertisers = $issue->advertisers(); @endphp
        @if ($advertisers !== [])
            <section class="magazine-advertisers">
                <div class="wrap">
                    <h2>In this issue: advertisers</h2>
                    <ul>
                        @foreach ($advertisers as $advertiser)
                            <li>
                                @if ($advertiser['logo'])
                                    <img src="{{ $advertiser['logo'] }}" alt="">
                                @endif
                                <span>{{ $advertiser['name'] }}</span>
                                <button type="button" data-magazine-jump="{{ $advertiser['page'] }}">Page {{ $advertiser['page'] }}</button>
                                @if ($advertiser['listing_url'])
                                    <a href="{{ $advertiser['listing_url'] }}">Directory listing</a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endif

        @if ($issue->pdfUrl())
            <noscript>
                <p class="empty">
                    The flipbook needs JavaScript.
                    @if ($issue->hasReadableText())
                        Scroll down to read the magazine as text.
                    @endif
                </p>
            </noscript>
        @endif

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

    @if ($issue->pdfUrl())
        @vite('resources/js/flipbook.js')
    @endif
</x-layouts.public>
