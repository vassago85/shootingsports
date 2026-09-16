<x-layouts.public
    title="Match calendar"
    description="Every listed match in South African shooting sport, filterable by discipline, province, and how far you will drive."
>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">The calendar</p>
                <h1>What's on, and where</h1>
                <p>Planned dates stay on the calendar and render as provisional. Confirmed dates look different.</p>
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
                    :discipline="$discipline"
                    :province="$province"
                    :radius="$radius"
                    :from="$from"
                    :to="$to"
                />
                <x-ad-slot page="calendar" placement-slot="leaderboard" :limit="2" class="ad-rail--tight" hide-when-vacant />
            </div>
        </section>
    </main>
</x-layouts.public>
