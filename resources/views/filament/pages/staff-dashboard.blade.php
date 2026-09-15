<x-filament-panels::page>
    <div class="desk-hero">
        <p class="label">Staff desk</p>
        <h1>Welcome, {{ $name }}</h1>
        <p>
            Publish clubs and series, moderate claims, run the calendar, and place ads.
            Match directors work from /desk — you publish from here.
        </p>
    </div>

    {{-- Pro waitlist demand-signal widget. Sits first because it is
         the metric the freemium foundation exists to produce, and it
         needs a scannable eye every day — not a click into Enquiries. --}}
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

    {{-- New match director signups this month. Signup is open so we
         surface the review queue here rather than blocking access —
         staff can pair each new MD with an existing organisation
         (or catch a bad actor early) from the linked UserResource. --}}
    <div class="desk-widget">
        <p class="kicker">New match directors · {{ now()->format('F Y') }}</p>
        @if ($newMatchDirectors === 0)
            <p style="color:var(--slate)">No new match director signups yet this month.</p>
        @else
            <p>
                <b>{{ $newMatchDirectors }}</b> new match director{{ $newMatchDirectors === 1 ? '' : 's' }} signed up.
                Check the accompanying MD signup enquiries in the inbox for any club/host they named,
                then pair them with an existing organisation.
            </p>
            <p style="margin-top:8px">
                <a href="{{ $usersUrl }}" style="color:var(--brass)">Review users →</a>
                &nbsp;·&nbsp;
                <a href="{{ $enquiriesUrl }}" style="color:var(--brass)">MD signup enquiries →</a>
            </p>
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
