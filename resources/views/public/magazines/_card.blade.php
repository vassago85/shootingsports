@php
    $readLabel = $issue->requiresLogin() ? 'Sign in to read' : ($hero ? 'Read now' : 'Read this issue');
    $month = $issue->issue_month?->format('F Y');
@endphp
<article class="magazine-card {{ $hero ? 'is-hero' : '' }}">
    <a class="magazine-card__cover" href="{{ route('magazines.show', $issue->slug) }}">
        @if ($issue->coverUrl())
            <img src="{{ $issue->coverUrl() }}" alt="{{ $issue->title }}">
        @else
            <span class="magazine-card__placeholder" aria-hidden="true"></span>
        @endif
    </a>
    <div class="magazine-card__body">
        <p class="label">{{ $issue->publication }}</p>
        <h2><a href="{{ route('magazines.show', $issue->slug) }}">{{ $issue->title }}</a></h2>
        <p class="magazine-card__meta">
            @if ($issue->issue_number)
                <span>Issue {{ $issue->issue_number }}</span>
            @endif
            @if ($month)
                <span>{{ $month }}</span>
            @elseif ($issue->issue_label)
                <span>{{ $issue->issue_label }}</span>
            @endif
            @if ($issue->page_count)
                <span>{{ $issue->page_count }} pages</span>
            @endif
        </p>
        @if ($publisher = $issue->publisherLabel())
            <p>Published by {{ $publisher }}</p>
        @endif
        @if ($continuePage)
            <p class="magazine-continue">Continue reading</p>
        @endif
        <p>
            <a class="btn" href="{{ route('magazines.show', $issue->slug) }}">{{ $readLabel }}</a>
        </p>
    </div>
</article>
