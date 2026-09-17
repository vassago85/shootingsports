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
                <p>Planned dates stay listed and render as provisional. Confirmed dates look different.</p>
                {{-- View toggle: List / Month / Map. Map is a Matches
                     view, not a separate top-level destination. --}}
                <div class="view-toggle" role="group" aria-label="Matches view">
                    <a href="{{ route('calendar', request()->query()) }}" aria-pressed="true">List</a>
                    <a href="{{ route('calendar.month', request()->query()) }}" aria-pressed="false">Month</a>
                    <a href="{{ route('map', request()->except(['month'])) }}" aria-pressed="false">Map</a>
                </div>
            </div>
        </section>
        <section class="block">
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
