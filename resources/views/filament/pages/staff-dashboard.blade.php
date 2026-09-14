<x-filament-panels::page>
    <div class="desk-hero">
        <p class="label">Staff desk</p>
        <h1>Welcome, {{ $name }}</h1>
        <p>
            Publish clubs and series, moderate claims, run the calendar, and place ads.
            Match directors work from /desk — you publish from here.
        </p>
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
