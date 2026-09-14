<!DOCTYPE html>
<html lang="en-ZA">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Match calendar · Shooting Sports</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}" sizes="16x16 32x32 48x48">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Saira+Condensed:wght@600&family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500&display=swap">
    @vite(['resources/css/app.css'])
</head>
<body>
    <main id="main" style="padding:16px">
        <p class="label">Shooting Sports calendar</p>
        @forelse ($events as $event)
            <a class="match-row" href="{{ route('matches.show', $event->slug) }}" target="_blank" rel="noopener">
                <div class="m-date">
                    <b>{{ $event->starts_at->timezone('Africa/Johannesburg')->format('j') }}</b>
                    {{ $event->starts_at->timezone('Africa/Johannesburg')->format('M') }}
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
