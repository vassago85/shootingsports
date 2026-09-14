<x-layouts.public
    title="My calendar"
    description="The matches you have added, ready to embed on your own site."
>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">Shooter calendar</p>
                <h1>My calendar</h1>
                <p>Add matches from the public calendar. Subscribe once and they land on your phone calendar when you add or remove them.</p>
            </div>
        </section>
        <section class="block">
            <div class="wrap">
                @if ($events->isEmpty())
                    <p class="empty">Nothing on your calendar yet. Open a match and tap Add to my calendar.</p>
                    <p><a class="btn" href="{{ route('calendar') }}">Browse the calendar</a></p>
                @else
                    <div class="dope-grid">
                        @foreach ($events as $event)
                            <x-event-card :event="$event" />
                        @endforeach
                    </div>
                @endif
                <x-calendar-subscribe :ics="route('ical.shooter', $user->calendar_slug)" />
                <x-embed-snippet :shooter="$user->calendar_slug" />
            </div>
        </section>
    </main>
</x-layouts.public>
