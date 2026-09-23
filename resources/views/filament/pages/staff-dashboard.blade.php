<x-filament-panels::page>
    <div class="desk-hero">
        <p class="label">Staff desk</p>
        <h1>ShootingSports Admin</h1>
        <p>What needs doing on the register. Welcome, {{ $name }}.</p>
    </div>

    <div class="admin-stats">
        @foreach ($stats as $stat)
            <a href="{{ $stat['href'] }}">
                <b>{{ $stat['value'] }}</b>
                <span>{{ $stat['label'] }}</span>
            </a>
        @endforeach
    </div>

    <section class="desk-widget">
        <p class="kicker">Needs attention</p>
        <div class="admin-badges">
            @foreach ($summary as $item)
                <span class="admin-badge {{ $item['severity'] }}">{{ $item['count'] }} {{ $item['label'] }}</span>
            @endforeach
        </div>
        @forelse ($attention as $item)
            <a class="admin-row" href="{{ $item['href'] }}">
                <span class="admin-row-title">{{ $item['title'] }}</span>
                <span class="admin-row-meta">{{ $item['problem'] }}</span>
                <span class="admin-badge {{ $item['severity'] }}">{{ $item['severity'] === 'attention' ? 'Needs attention' : ucfirst($item['severity']) }}</span>
            </a>
        @empty
            <p class="admin-quiet">Nothing is flagged.</p>
        @endforelse
        <p class="admin-more"><a href="{{ $verificationUrl }}">Open the verification queue</a></p>
    </section>

    <section class="desk-widget">
        <p class="kicker">Upcoming activity</p>
        <ul class="admin-activity">
            @foreach ($activity as $item)
                <li><time>{{ $item['when'] }}</time><span>{{ $item['text'] }}</span></li>
            @endforeach
        </ul>
        <h2 class="admin-subhead">Newest records</h2>
        @forelse ($newest as $record)
            <p class="admin-line"><a href="{{ $record['href'] }}">{{ $record['label'] }}</a></p>
        @empty
            <p class="admin-quiet">No matches on the register yet.</p>
        @endforelse
    </section>

    <section class="desk-widget">
        <p class="kicker">Recent submissions</p>
        @if ($submissions === [])
            <p class="admin-quiet">The submissions queue is empty. Club claims, match edits, and range corrections land here, along with matches people submit before approval.</p>
        @else
            @foreach ($submissions as $item)
                <a class="admin-row" href="{{ $item['href'] }}">
                    <span class="admin-row-title">{{ $item['label'] }}</span>
                    <span class="admin-row-meta">{{ $item['meta'] }}</span>
                </a>
            @endforeach
        @endif
        <p class="admin-more">
            <a href="{{ $pendingMatchesUrl }}">Submitted matches</a>
            · <a href="{{ $claimsUrl }}">Claims</a>
            · <a href="{{ $submissionsUrl }}">Corrections</a>
        </p>
    </section>

    <section class="desk-widget">
        <p class="kicker">Platform activity</p>
        <p>
            {{ $platform['users'] }} user accounts.
            {{ $platform['follows'] }} follows.
            Advertising has {{ number_format($platform['impressions']) }} impressions and {{ number_format($platform['clicks']) }} clicks.
        </p>
        <p class="admin-follow">
            @if ($proSubscribers['total'] === 0)
                No active paid subscribers yet.
            @else
                <b>{{ $proSubscribers['total'] }}</b> paid subscribers
                ({{ $proSubscribers['annual'] }} annual, {{ $proSubscribers['monthly'] }} monthly)
                · est. MRR <b>R{{ number_format($estimatedMrrCents / 100, 0) }}</b>
            @endif
            @unless ($proSalesOpen)
                Pro sales are held.
            @endunless
            <a href="{{ $usersUrl }}">Manage subscribers</a>
        </p>
        <p class="admin-follow">
            Pro waitlist · {{ now()->format('F Y') }}
            @if (empty($waitlistThisMonth))
                — no signups yet this month.
            @endif
        </p>
        @if (! empty($waitlistThisMonth))
            <ul class="waitlist-triggers">
                @foreach ($waitlistThisMonth as $trigger => $count)
                    <li>
                        <span class="trigger">{{ str_replace('_', ' ', $trigger) }}</span>
                        <b>{{ $count }}</b>
                    </li>
                @endforeach
            </ul>
        @endif
        <p class="admin-follow">
            Match directors: {{ $mdRequestsPending }} awaiting review, {{ $mdApprovedThisMonth }} approved this month.
            @if ($mdRequestsPending > 0)
                <a href="{{ $mdPendingUrl }}">Review pending users</a>
                · <a href="{{ $enquiriesUrl }}">MD signup enquiries</a>
            @endif
        </p>
        <p class="admin-more">
            <a href="{{ $emailLogUrl }}">Email log</a>
            · <a href="{{ $pagePicturesUrl }}">Page pictures</a>
            · <a href="{{ $placementsUrl }}">Placements</a>
            · <a href="{{ $duplicatesUrl }}">Venue duplicates</a>
            · <a href="{{ $emailUrl }}">Email &amp; Turnstile</a>
        </p>
    </section>

    <section class="desk-widget">
        <p class="kicker">Quick actions</p>
        <div class="admin-actions">
            @foreach ($actions as $action)
                <a @class(['admin-action', 'is-primary' => $action['primary']]) href="{{ $action['href'] }}">{{ $action['label'] }}</a>
            @endforeach
        </div>
    </section>

    <p class="desk-note">
        Public site → shootingsports.co.za · Directors → /desk ·
        <a href="{{ $orgsUrl }}">All organisations</a>
        · <a href="{{ $eventsUrl }}">All events</a>
    </p>
</x-filament-panels::page>
