<!DOCTYPE html>
<html lang="en-ZA" @if ($theme->theme) data-theme="{{ $theme->theme }}" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <link rel="alternate" type="application/json+oembed" href="{{ $oembedUrl }}" title="Shooting Sports oEmbed">
    <title>{{ $listingName ? $listingName.' calendar' : 'Match calendar' }} · Shooting Sports</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16.png') }}">
    @if ($theme->googleFontsHref())
        <link rel="stylesheet" href="{{ $theme->googleFontsHref() }}">
    @endif
    @vite(['resources/css/app.css'])
    <style>
        :root { {{ $theme->cssVariables() }} }
        html, body { background: var(--base); }
        .embed-shell { padding: 16px; }
    </style>
</head>
<body>
    <main id="main" class="embed-shell">
        <p class="label">{{ $listingName ? $listingName : 'Shooting Sports' }} calendar</p>
        @forelse ($events as $event)
            <a class="match-row" href="{{ route('matches.show', $event->slug) }}" target="_blank" rel="noopener">
                <div class="m-date">
                    <span class="dow">{{ \App\Support\EventDate::weekday($event->starts_at, $event->ends_at) }}</span>
                    <b>{{ \App\Support\EventDate::dayOfMonth($event->starts_at, $event->ends_at) }}</b>
                    <span class="mo">{{ \App\Support\EventDate::monthWithYear($event->starts_at, $event->ends_at) }}</span>
                </div>
                <div class="m-body">
                    <div class="t">{{ $event->title }}</div>
                    <div class="m">{{ $event->locationLabel() }}</div>
                </div>
                <div class="m-pills">
                    <x-event-status-pill :event="$event" />
                </div>
            </a>
        @empty
            <p class="empty">No upcoming matches.</p>
        @endforelse
        <p style="margin-top:16px"><a class="label" href="{{ route('calendar') }}" target="_blank" rel="noopener" style="border-bottom:1px solid var(--brass);text-decoration:none">Open the full calendar →</a></p>
    </main>
</body>
</html>
