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
                {{-- Post-signup / persistent flash for match director
                     requests. Session flash covers the first render right
                     after the redirect from /directors/register; the
                     mdStatus() check keeps the banner around on every
                     subsequent /my-calendar visit until staff decide. --}}
                @if (session('status'))
                    <div class="pending-banner" role="status">
                        {{ session('status') }}
                    </div>
                @elseif ($user->isMdPending())
                    <div class="pending-banner" role="status">
                        <b>Match director access pending.</b>
                        Your request is with staff for review — usually approved within one working day. We will email you as soon as it is done. Your shooter calendar (below) works as normal.
                    </div>
                @elseif ($user->isMdRejected())
                    <div class="pending-banner" role="status" style="border-color:#f0776b">
                        <b>Match director request was not approved.</b>
                        Your shooter account still works. If you think this was a mistake, reply to the email we sent you or <a href="{{ route('contact') }}">contact us</a>.
                    </div>
                @endif

                {{-- Pro trial in-progress banner. Sits at the top of the
                     calendar so the countdown is always visible without
                     forcing users to visit /upgrade to see how much time
                     they have left. Only shows during an active trial
                     (not for paid Pro, not after the trial expires). --}}
                @if ($user->isOnTrial())
                    <div class="trial-progress" role="status">
                        <p class="pricing-label">Pro trial · {{ $user->trialDaysRemaining() }} {{ $user->trialDaysRemaining() === 1 ? 'day' : 'days' }} left</p>
                        <p>
                            You're on Pro until {{ $user->plan_expires_at->format('j M Y') }} — unlimited follows, saved searches and attendance-log entries.
                            <a href="{{ route('upgrade') }}"><b>Add a card</b></a> to keep Pro after that. No auto-billing — we never took a card in the first place.
                        </p>
                    </div>
                @endif

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
