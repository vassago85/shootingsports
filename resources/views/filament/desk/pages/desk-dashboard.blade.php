<x-filament-panels::page>
    <div class="ss-desk ss-desk-{{ $mode }}">
        @if ($mode === 'none')
            <div class="ss-noaccess">
                <div class="ss-eyebrow">Desk</div>
                <h1 class="ss-h1">Your desk opens once you manage a listing</h1>
                <p class="ss-lede">
                    Match directors, club admins and businesses use the desk to keep their
                    listings and events up to date. Link yours to get started.
                </p>

                <div class="ss-grid2">
                    <div class="ss-card">
                        <h3>Run matches?</h3>
                        <p class="ss-muted">Claim your club or series, or add a new one. Staff approve within a day.</p>
                        <a class="ss-btn" href="{{ $claimUrl }}">Claim a club</a>
                    </div>
                    <div class="ss-card">
                        <h3>Have a business?</h3>
                        <p class="ss-muted">Gunsmith, dealer, ammo, optics, instructor. Listing is free.</p>
                        <a class="ss-btn ss-btn-ghost" href="{{ $listBusinessUrl }}">List your business</a>
                    </div>
                </div>

                <p class="ss-foot">
                    Just here to shoot? <a href="{{ $calendarUrl }}">Go to my calendar</a>
                </p>
            </div>
        @elseif ($mode === 'director')
            <div class="ss-hdr">
                <div>
                    <div class="ss-eyebrow">Match director desk</div>
                    <h1 class="ss-h1">Welcome back</h1>
                </div>
                <a class="ss-btn" href="{{ $createEventUrl }}">Add event</a>
            </div>

            <div class="ss-grid3">
                <a class="ss-card ss-tile" href="{{ $listingsUrl }}">
                    <div class="ss-n">{{ $clubCount }}</div>
                    <div class="ss-l">Clubs you manage</div>
                </a>
                <a class="ss-card ss-tile" href="{{ $eventsUrl }}">
                    <div class="ss-n">{{ $upcomingCount }}</div>
                    <div class="ss-l">Upcoming events</div>
                </a>
                <a class="ss-card ss-tile" href="{{ $eventsUrl }}">
                    <div class="ss-n">{{ $pendingReviewCount }}</div>
                    <div class="ss-l">Waiting for staff review</div>
                </a>
            </div>

            <div class="ss-card ss-mt">
                <h3>Upcoming events</h3>
                @if (empty($upcomingEvents))
                    <p class="ss-muted">No upcoming events yet. <a href="{{ $createEventUrl }}">Add your first</a>.</p>
                @else
                    <div class="ss-table-wrap">
                        <table class="ss-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Event</th>
                                    <th>Status</th>
                                    <th>Listing</th>
                                    <th style="width:11rem">Complete</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($upcomingEvents as $event)
                                    <tr>
                                        <td>{{ $event['date'] }}</td>
                                        <td>
                                            <a href="{{ $event['href'] }}" class="ss-link">{{ $event['title'] }}</a>
                                            @if ($event['host'])
                                                <br><span class="ss-muted">{{ $event['host'] }}</span>
                                            @endif
                                        </td>
                                        <td><span class="ss-sev ss-sev-{{ $event['status_color'] }}">{{ $event['status'] }}</span></td>
                                        <td><span class="ss-sev ss-sev-{{ $event['publication_color'] }}">{{ $event['publication'] }}</span></td>
                                        <td>
                                            {{ $event['percent'] }}%
                                            <div class="ss-bar"><b style="width: {{ $event['percent'] }}%"></b></div>
                                            @if ($event['percent'] < 100 && ! empty($event['hints']))
                                                <div class="ss-hint">{{ implode(' · ', $event['hints']) }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="ss-grid2 ss-mt">
                <div class="ss-card">
                    <h3>Your clubs</h3>
                    @forelse ($clubs as $club)
                        <div class="ss-row">
                            <span>
                                <a href="{{ $club['href'] }}" class="ss-link">{{ $club['name'] }}</a>
                                <span class="ss-muted">· {{ strtolower($club['role']) }}</span>
                            </span>
                            <span class="ss-sev {{ $club['verified'] ? 'ss-sev-success' : 'ss-sev-gray' }}">
                                {{ $club['status_label'] }}
                            </span>
                        </div>
                    @empty
                        <p class="ss-muted">No clubs linked yet.</p>
                        <div class="ss-row">
                            <span class="ss-muted">Claim an existing club or add a new one</span>
                            <span>
                                <a class="ss-btn ss-btn-sm ss-btn-ghost" href="{{ $claimUrl }}">Claim</a>
                                <a class="ss-btn ss-btn-sm" href="{{ $createListingUrl }}">Add</a>
                            </span>
                        </div>
                    @endforelse
                    @if (! empty($clubs))
                        <div class="ss-row">
                            <span class="ss-muted">Owners can invite editors by email</span>
                            <span class="ss-btn ss-btn-sm ss-btn-ghost ss-btn-disabled" title="Coming soon">Invite</span>
                        </div>
                    @endif
                </div>

                <div class="ss-card">
                    <h3>How publishing works</h3>
                    <p class="ss-muted" style="margin:0">
                        New events go to staff for a quick check and usually appear within a day.
                        Clubs that staff mark as trusted publish straight away. You can change
                        status (confirmed, entries open, postponed, cancelled) any time.
                    </p>
                </div>
            </div>
        @elseif ($mode === 'industry')
            <div class="ss-hdr">
                <div>
                    <div class="ss-eyebrow">Industry desk</div>
                    <h1 class="ss-h1">{{ $businessName }}</h1>
                </div>
                <a class="ss-btn" href="{{ $editUrl }}">Edit listing</a>
            </div>

            <div class="ss-grid3">
                <div class="ss-card ss-tile">
                    <div class="ss-n">{{ number_format($impressions) }}</div>
                    <div class="ss-l">Advert views</div>
                </div>
                <div class="ss-card ss-tile">
                    <div class="ss-n">{{ number_format($clicks) }}</div>
                    <div class="ss-l">Advert clicks</div>
                </div>
                <div class="ss-card ss-tile">
                    <div class="ss-n">{{ $enquiriesThisMonth }}</div>
                    <div class="ss-l">Enquiries this month</div>
                </div>
            </div>

            <div class="ss-card ss-mt">
                <h3>Listing health</h3>
                @foreach ($health as $row)
                    <div class="ss-row">
                        <span>{{ $row['label'] }}</span>
                        @if (! empty($row['action']))
                            <a class="ss-btn ss-btn-sm" href="{{ $row['action'] }}">Confirm now</a>
                        @else
                            <span class="ss-sev {{ $row['ok'] ? 'ss-sev-success' : 'ss-sev-warning' }}">
                                {{ $row['status'] }}
                            </span>
                        @endif
                    </div>
                @endforeach
            </div>

            <p class="ss-foot ss-foot-left">
                Want more reach? <a href="{{ $advertiseUrl }}">Advertise with us</a>
            </p>
        @elseif ($mode === 'media')
            <div class="ss-hdr">
                <div>
                    <div class="ss-eyebrow">Media desk</div>
                    <h1 class="ss-h1">Welcome, {{ $name }}</h1>
                </div>
            </div>
            <div class="ss-card">
                <h3>Your articles</h3>
                <p class="ss-muted">Draft, submit and track pieces that appear on the public site.</p>
                <a class="ss-btn" href="{{ $articlesUrl }}">Open articles</a>
            </div>
        @endif
    </div>
</x-filament-panels::page>
