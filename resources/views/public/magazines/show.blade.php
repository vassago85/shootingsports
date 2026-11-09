<x-layouts.public
    :title="$seo->title"
    :description="$seo->description"
    :canonical="$seo->canonical"
    :robots="$seo->robots"
    :image="$issue->coverUrl()"
    :json-ld="$jsonLd"
>
    <main id="main" class="magazine-show">
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
                @if ($publisher = $issue->publisherLabel())
                    <p>Published by {{ $publisher }}</p>
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
            <div class="wrap" style="max-width:800px">
                @if ($issue->pdfUrl())
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

                    <div class="magazine-stage__bar">
                        <button type="button" class="btn ghost magazine-control magazine-control--screen" id="magazine-fullscreen" aria-pressed="false">Full screen</button>
                        @if (! $preview && $issue->isPublished())
                            <livewire:save-magazine-issue :issue="$issue" :key="'save-issue-'.$issue->id" />
                        @endif
                        @if ($issue->hasReadableText())
                            <a class="btn ghost magazine-control magazine-control--text" href="#magazine-text">Read as text</a>
                        @endif
                    </div>

                    @if ($issue->allowsPdfDownload() && ! $preview)
                        <form method="post" action="{{ route('magazines.download-link', $issue->slug) }}" class="enquiry-form" style="margin-top:18px;max-width:420px">
                            @csrf
                            <div class="hp" aria-hidden="true">
                                <label for="company_website-{{ $issue->id }}">Company website</label>
                                <input type="text" name="company_website" id="company_website-{{ $issue->id }}" value="" tabindex="-1" autocomplete="off">
                            </div>
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
                        </form>
                    @endif
                </div>

                <noscript>
                    <p class="empty" style="margin-top:16px">
                        The flipbook needs JavaScript.
                        @if ($issue->hasReadableText())
                            Scroll down to read the magazine as text.
                        @endif
                    </p>
                </noscript>
                @else
                    <p class="empty">This issue is not on the reader yet.</p>
                @endif
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

    @if ($issue->pdfUrl())
        @vite('resources/js/flipbook.js')
    @endif
</x-layouts.public>
