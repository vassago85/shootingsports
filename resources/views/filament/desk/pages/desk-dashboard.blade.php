<x-filament-panels::page>
    <div class="desk-hero">
        <p class="label">Match director desk</p>
        <h1>Welcome, {{ $name }}</h1>
        <p>
            List your club or series, claim an existing one, then add matches.
            New listings stay private until staff publish them.
        </p>
    </div>

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

    <p class="desk-note">
        Public site → shootingsports.co.za · Staff publish listings from /admin · You are not a firearms dealer listing.
    </p>
</x-filament-panels::page>
