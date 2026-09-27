<x-layouts.public
    :title="$user->name.' calendar'"
    :description="'Upcoming matches '.$user->name.' has added to their Shooting Sports calendar.'"
>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">Shooter</p>
                <h1>{{ $user->name }}</h1>
                <p>{{ $profile['starts'] }} events · {{ $profile['podiums'] }} podiums · {{ $profile['wins'] }} wins. From results recorded on this site.</p>
                @if ($profile['disciplines']->isNotEmpty())
                    <p>
                        @foreach ($profile['disciplines'] as $discipline)
                            <a href="{{ route('disciplines.show', $discipline) }}">{{ $discipline->name }}</a>@if (! $loop->last), @endif
                        @endforeach
                    </p>
                @endif
            </div>
        </section>
        @if ($equipment->isNotEmpty())
            <section class="block">
                <div class="wrap">
                    <h2>Equipment</h2>
                    <ul>
                        @foreach ($equipment as $item)
                            <li>{{ $item->category->getLabel() }} — {{ $item->label }}</li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endif
        @if ($upcomingEntries->isNotEmpty())
            <section class="block">
                <div class="wrap">
                    <h2>Entered</h2>
                    <ul>
                        @foreach ($upcomingEntries as $entry)
                            <li><a href="{{ route('events.show', $entry->event->slug) }}">{{ $entry->event->title }}</a></li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endif
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
