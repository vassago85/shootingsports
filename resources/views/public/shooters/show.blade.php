<x-layouts.public
    :title="$user->name.' calendar'"
    :description="'Upcoming matches '.$user->name.' has added to their Shooting Sports calendar.'"
>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">Shooter calendar</p>
                <h1>{{ $user->name }}</h1>
                <p>Matches this shooter has added from the register.</p>
            </div>
        </section>
        <section class="block">
            <div class="wrap">
                @if ($events->isEmpty())
                    <p class="empty">No upcoming matches on this calendar.</p>
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
