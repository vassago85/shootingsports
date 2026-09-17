<x-layouts.public
    :title="$seoTitle"
    :description="$seoDescription"
    :canonical="$canonical"
    :json-ld="$jsonLd"
>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">Matches</p>
                <h1>What's on, and where</h1>
                <p>South Africa's national match programme. Planned dates render as provisional; confirmed dates look different.</p>
            </div>
        </section>
        {{--
            Match board — dark surface for the results list, sticky filter
            toolbar, dense horizontal rows. The Livewire component owns the
            toolbar, active-filter chips, More filters panel, and the List /
            Month / Map view toggle (which forwards the current query string).
        --}}
        <section class="match-board">
            <div class="wrap">
                {{-- UX audit #13: ad-slot moved from above-the-filters
                     to below the results, and gated with
                     hide-when-vacant so the house pitch does not sit
                     at the top of the page above the actual matches. --}}
                <livewire:calendar-filter
                    :family="$family"
                    :novice="$novice"
                    :confirmed="$confirmed"
                    :weekend="$weekend"
                    :discipline="$discipline"
                    :province="$province"
                    :radius="$radius"
                    :near="$near ?? null"
                    :lat="$lat ?? null"
                    :lng="$lng ?? null"
                    :from="$from"
                    :to="$to"
                />
                <x-ad-slot page="calendar" placement-slot="leaderboard" :limit="2" class="ad-rail--tight" hide-when-vacant />
            </div>
        </section>
    </main>
</x-layouts.public>
