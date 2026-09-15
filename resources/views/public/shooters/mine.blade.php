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
                @if ($events->isEmpty() && $past->isEmpty())
                    <p class="empty">Nothing on your calendar yet. Open a match and tap Add to my calendar.</p>
                    <p><a class="btn" href="{{ route('calendar') }}">Browse the calendar</a></p>
                @else
                    @if ($events->isNotEmpty())
                        <h2 class="section-title">Upcoming</h2>
                        <div class="dope-grid">
                            @foreach ($events as $event)
                                <x-event-card :event="$event" />
                            @endforeach
                        </div>
                    @endif

                    @if ($past->isNotEmpty() || $historyClipped)
                        <h2 class="section-title" style="margin-top:36px">Past</h2>
                        <div class="dope-grid">
                            @foreach ($past as $event)
                                <x-event-card :event="$event" />
                            @endforeach
                        </div>

                        {{-- Data is still in the DB — free tier just does
                             not render past the horizon. The cut-off row
                             names the boundary honestly and can open
                             the shared UpgradePrompt modal via event. --}}
                        @if ($historyClipped)
                            <livewire:upgrade-prompt
                                :trigger="'history_window'"
                                as="cutoff-row"
                                :key="'history-cutoff'"
                            />
                        @endif
                    @endif
                @endif
                <x-calendar-subscribe :ics="route('ical.shooter', $user->calendar_slug)" />
                <x-embed-snippet :shooter="$user->calendar_slug" />
            </div>
        </section>
    </main>
</x-layouts.public>
