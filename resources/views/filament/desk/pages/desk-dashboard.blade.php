<x-filament-panels::page>
    <div class="desk-hero">
        <p class="label">Match director desk</p>
        <h1>Welcome, {{ $name }}</h1>
        <p>
            List your club or series, claim an existing one, then add matches.
            New listings stay private until staff publish them.
            You can be a match director on several clubs and federations at once.
        </p>
    </div>

    {{-- Snapshot of what this MD actually has on the desk right now.
         Only rendered when there is something to look at, so a fresh
         director does not see a wall of zeros. --}}
    @if ($listingCount > 0 || $eventCount > 0)
        <div class="admin-stats">
            <a href="{{ $listingsUrl }}">
                <b>{{ $listingCount }}</b>
                <span>Listing{{ $listingCount === 1 ? '' : 's' }}</span>
            </a>
            <a href="{{ $eventsUrl }}">
                <b>{{ $upcomingCount }}</b>
                <span>Upcoming</span>
            </a>
            <a href="{{ $eventsUrl }}">
                <b>{{ $draftCount }}</b>
                <span>Draft{{ $draftCount === 1 ? '' : 's' }}</span>
            </a>
        </div>
    @endif

    <div class="desk-grid">
        <a class="desk-card" href="{{ $createListingUrl }}">
            <p class="kicker">01 — New listing</p>
            <h2>Create club or series</h2>
            <p>Royal Flush–style series, clubs, associations. Draft until published.</p>
        </a>

        <a class="desk-card" href="{{ $claimUrl }}">
            <p class="kicker">02 — Existing</p>
            <h2>Claim a listing</h2>
            <p>Already on the register? Request ownership. Staff approve before you can edit.</p>
        </a>

        <a class="desk-card" href="{{ $createEventUrl }}">
            <p class="kicker">03 — Calendar</p>
            <h2>Add a match</h2>
            <p>Title, date, fees, banner. Host must be one of your listings.</p>
        </a>

        <a class="desk-card" href="{{ $listingsUrl }}">
            <p class="kicker">Your desk</p>
            <h2>{{ $listingCount }} listing{{ $listingCount === 1 ? '' : 's' }}</h2>
            <p>{{ $eventCount }} match{{ $eventCount === 1 ? '' : 'es' }} under your care. Manage them here.</p>
        </a>
    </div>

    {{-- Next up: five soonest upcoming matches on this MD's desk.
         Skipped entirely when there are none — nothing worse than a
         "No matches" placeholder cluttering an empty new-MD page. --}}
    @if (! empty($nextEvents))
        <section class="desk-widget">
            <p class="kicker">Next matches on your desk</p>
            @foreach ($nextEvents as $event)
                <a class="admin-row" href="{{ $event['href'] }}">
                    <span class="admin-row-title">{{ $event['title'] }}</span>
                    <span class="admin-row-meta">{{ $event['date'] }} · {{ $event['status'] }}</span>
                </a>
            @endforeach
            <p class="admin-more"><a href="{{ $eventsUrl }}">All your matches →</a></p>
        </section>
    @endif

    <p class="desk-note">
        Public site → shootingsports.co.za · Staff publish listings from /admin · You are not a firearms dealer listing.
    </p>
</x-filament-panels::page>
