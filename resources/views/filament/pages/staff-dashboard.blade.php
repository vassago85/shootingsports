<x-filament-panels::page>
    <div class="desk-hero">
        <p class="label">Staff desk</p>
        <h1>Welcome, {{ $name }}</h1>
        <p>
            Publish clubs and series, moderate claims, run the calendar, and place ads.
            Match directors work from /desk — you publish from here.
        </p>
    </div>

    {{-- Paid subscriber counter + rough MRR. Comps (no
         paystack_subscription_code) are excluded — they are not
         recurring revenue. Annuals are amortised over 12 months at
         the currently configured annual price. --}}
    <div class="desk-widget">
        <p class="kicker">Pro subscribers · live</p>
        @if ($proSubscribers['total'] === 0)
            <p style="color:var(--slate)">No active paid subscribers yet.</p>
        @else
            <p>
                <b>{{ $proSubscribers['total'] }}</b> active
                ({{ $proSubscribers['annual'] }} annual, {{ $proSubscribers['monthly'] }} monthly)
                · est. MRR <b>R{{ number_format($estimatedMrrCents / 100, 0) }}</b>
            </p>
            <p style="margin-top:8px">
                <a href="{{ $usersUrl }}" style="color:var(--brass)">Manage subscribers →</a>
            </p>
        @endif
    </div>

    {{-- Pro waitlist demand-signal widget. Kept alongside the paid
         subscriber count because a healthy waitlist number after
         launch still tells us where the frictions are. --}}
    <div class="desk-widget">
        <p class="kicker">Pro waitlist · {{ now()->format('F Y') }}</p>
        @if (empty($waitlistThisMonth))
            <p style="color:var(--slate)">No waitlist signups yet this month.</p>
        @else
            <ul class="waitlist-triggers">
                @foreach ($waitlistThisMonth as $trigger => $count)
                    <li>
                        <span class="trigger">{{ str_replace('_', ' ', $trigger) }}</span>
                        <b>{{ $count }}</b>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- MD review queue. Pending count is the action item; the
         month-to-date approvals figure is throughput signal. Approve
         and reject are record actions inside UserResource — this
         widget just points there. --}}
    <div class="desk-widget"@if ($mdRequestsPending > 0) style="border-color:var(--brass)" @endif>
        <p class="kicker">Match director requests</p>
        @if ($mdRequestsPending === 0)
            <p style="color:var(--slate)">
                Queue is empty. <b>{{ $mdApprovedThisMonth }}</b> approved this month.
            </p>
        @else
            <p>
                <b>{{ $mdRequestsPending }}</b> request{{ $mdRequestsPending === 1 ? '' : 's' }} awaiting review.
                Read the applicant's host hint (in the linked MD signup enquiry), then approve or reject from the user row.
            </p>
            <p style="margin-top:8px">
                <a href="{{ $mdPendingUrl }}" style="color:var(--brass)">Review pending users →</a>
                &nbsp;·&nbsp;
                <a href="{{ $enquiriesUrl }}" style="color:var(--brass)">MD signup enquiries →</a>
            </p>
            <p style="margin-top:6px;color:var(--slate);font-size:13px">{{ $mdApprovedThisMonth }} approved this month.</p>
        @endif
    </div>

    <div class="desk-grid">
        <a class="desk-card" href="{{ $createOrgUrl }}">
            <p class="kicker">01 — Directory</p>
            <h2>Clubs &amp; series</h2>
            <p>{{ $pendingOrgs }} pending listing{{ $pendingOrgs === 1 ? '' : 's' }}. Create or publish clubs, series, federations.</p>
        </a>

        <a class="desk-card" href="{{ $enquiriesUrl }}">
            <p class="kicker">02 — Inbox</p>
            <h2>Enquiries</h2>
            <p>{{ $newEnquiries }} new. Platform contact, advertise interest, and listing enquiries.</p>
        </a>

        <a class="desk-card" href="{{ $createEventUrl }}">
            <p class="kicker">03 — Calendar</p>
            <h2>Events</h2>
            <p>{{ $upcoming }} upcoming on the public calendar. Add or edit staff-owned matches.</p>
        </a>

        <a class="desk-card" href="{{ $placementsUrl }}">
            <p class="kicker">04 — Advertising</p>
            <h2>Placements</h2>
            <p>Slot catalog and creatives. Invoice off-site; mark active when paid.</p>
        </a>

        <a class="desk-card" href="{{ $verificationUrl }}">
            <p class="kicker">05 — Moderation</p>
            <h2>Verification</h2>
            <p>Ageing listings, claims, and freshness on the register.</p>
        </a>

        <a class="desk-card" href="{{ $emailUrl }}">
            <p class="kicker">06 — Settings</p>
            <h2>Email</h2>
            <p>Mailgun domain, from address, and test send. Overrides .env at runtime.</p>
        </a>
    </div>

    <p class="desk-note">
        Public site → shootingsports.co.za · Directors → /desk · Browse all organisations →
        <a href="{{ $orgsUrl }}" style="color:var(--brass)">{{ $orgsUrl }}</a>
        · Events →
        <a href="{{ $eventsUrl }}" style="color:var(--brass)">list</a>
    </p>
</x-filament-panels::page>
