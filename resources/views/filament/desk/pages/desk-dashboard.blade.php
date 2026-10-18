<x-filament-panels::page>
    @if ($mode === 'none')
        <div class="desk-no-access">
            <p class="label">Desk</p>
            <h1>Your desk opens once you manage a listing</h1>
            <p class="lede">
                Match directors, club admins and businesses use the desk to keep their
                listings and events up to date. Link yours to get started.
            </p>

            <div class="desk-grid desk-grid-2">
                <div class="desk-card desk-card-static">
                    <h2>Run matches?</h2>
                    <p>Claim your club or series, or add a new one. Staff approve within a day.</p>
                    <a class="desk-btn" href="{{ $claimUrl }}">Claim a club</a>
                </div>
                <div class="desk-card desk-card-static">
                    <h2>Have a business?</h2>
                    <p>Gunsmith, dealer, ammo, optics, instructor. Listing is free.</p>
                    <a class="desk-btn desk-btn-ghost" href="{{ $listBusinessUrl }}">List your business</a>
                </div>
            </div>

            <p class="desk-note">
                Just here to shoot? <a href="{{ $calendarUrl }}">Go to my calendar</a>
            </p>
        </div>
    @elseif ($mode === 'director')
        <div class="desk-hero desk-hero-row">
            <div>
                <p class="label">Match director desk</p>
                <h1>Welcome back</h1>
            </div>
            <a class="desk-btn" href="{{ $createEventUrl }}">Add event</a>
        </div>

        <div class="admin-stats admin-stats-3">
            <a href="{{ $listingsUrl }}">
                <b>{{ $clubCount }}</b>
                <span>Clubs you manage</span>
            </a>
            <a href="{{ $eventsUrl }}">
                <b>{{ $upcomingCount }}</b>
                <span>Upcoming events</span>
            </a>
            <a href="{{ $eventsUrl }}" @class(['admin-stat-warning' => $pendingReviewCount > 0])>
                <b>{{ $pendingReviewCount }}</b>
                <span>Waiting for staff review</span>
            </a>
        </div>

        <section class="desk-widget">
            <p class="kicker">Upcoming events</p>
            @forelse ($upcomingEvents as $event)
                <a class="desk-event-row" href="{{ $event['href'] }}">
                    <span class="desk-event-date">{{ $event['date'] }}</span>
                    <span class="desk-event-main">
                        <strong>{{ $event['title'] }}</strong>
                        @if ($event['host'])
                            <span class="muted">{{ $event['host'] }}</span>
                        @endif
                    </span>
                    <span class="desk-event-badges">
                        <span class="desk-sev desk-sev-{{ $event['status_color'] }}">{{ $event['status'] }}</span>
                        <span class="desk-sev desk-sev-{{ $event['publication_color'] }}">{{ $event['publication'] }}</span>
                    </span>
                    <span class="desk-complete">
                        <span class="desk-complete-label">{{ $event['percent'] }}%</span>
                        <span class="desk-bar"><i style="width: {{ $event['percent'] }}%"></i></span>
                        @if ($event['percent'] < 100 && ! empty($event['hints']))
                            <span class="desk-hint">{{ implode(' · ', $event['hints']) }}</span>
                        @endif
                    </span>
                </a>
            @empty
                <p class="muted">No upcoming events yet. <a href="{{ $createEventUrl }}">Add your first</a>.</p>
            @endforelse
        </section>

        <div class="desk-grid desk-grid-2" style="margin-top: 1rem">
            <section class="desk-widget">
                <p class="kicker">Your clubs</p>
                @forelse ($clubs as $club)
                    <a class="admin-row" href="{{ $club['href'] }}">
                        <span class="admin-row-title">
                            {{ $club['name'] }}
                            <span class="muted">· {{ strtolower($club['role']) }}</span>
                        </span>
                        <span class="desk-sev {{ $club['verified'] ? 'desk-sev-success' : 'desk-sev-gray' }}">
                            {{ $club['status_label'] }}
                        </span>
                    </a>
                @empty
                    <p class="muted">No clubs linked yet.</p>
                    <p class="admin-more">
                        <a href="{{ $claimUrl }}">Claim a club</a>
                        ·
                        <a href="{{ $createListingUrl }}">Add a new one</a>
                    </p>
                @endforelse
            </section>

            <section class="desk-widget">
                <p class="kicker">How publishing works</p>
                <p class="muted" style="margin: 0">
                    New events go to staff for a quick check and usually appear within a day.
                    Clubs that staff mark as trusted publish straight away. You can change
                    status (confirmed, entries open, postponed, cancelled) any time.
                </p>
            </section>
        </div>
    @elseif ($mode === 'industry')
        <div class="desk-hero desk-hero-row">
            <div>
                <p class="label">Industry desk</p>
                <h1>{{ $businessName }}</h1>
            </div>
            <a class="desk-btn" href="{{ $editUrl }}">Edit listing</a>
        </div>

        <div class="admin-stats admin-stats-3">
            <div class="admin-stat-static">
                <b>{{ number_format($impressions) }}</b>
                <span>Advert views</span>
            </div>
            <div class="admin-stat-static">
                <b>{{ number_format($clicks) }}</b>
                <span>Advert clicks</span>
            </div>
            <div class="admin-stat-static">
                <b>{{ $enquiriesThisMonth }}</b>
                <span>Enquiries this month</span>
            </div>
        </div>

        <section class="desk-widget">
            <p class="kicker">Listing health</p>
            @foreach ($health as $row)
                <div class="admin-row">
                    <span class="admin-row-title">{{ $row['label'] }}</span>
                    @if (! empty($row['action']))
                        <a class="desk-btn desk-btn-sm" href="{{ $row['action'] }}">Confirm now</a>
                    @else
                        <span class="desk-sev {{ $row['ok'] ? 'desk-sev-success' : 'desk-sev-warning' }}">
                            {{ $row['status'] }}
                        </span>
                    @endif
                </div>
            @endforeach
        </section>

        <p class="desk-note">
            Want more reach? <a href="{{ $advertiseUrl }}">Advertise with us</a>
        </p>
    @elseif ($mode === 'media')
        <div class="desk-hero">
            <p class="label">Media desk</p>
            <h1>Welcome, {{ $name }}</h1>
            <p>Submit articles and magazine content for staff review.</p>
        </div>
        <div class="desk-grid">
            <a class="desk-card" href="{{ $articlesUrl }}">
                <p class="kicker">Stories</p>
                <h2>Your articles</h2>
                <p>Draft, submit and track pieces that appear on the public site.</p>
            </a>
        </div>
    @endif
</x-filament-panels::page>
