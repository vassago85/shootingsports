<x-layouts.public
    :title="$seoTitle"
    :description="$seoDescription"
    :canonical="$canonical"
    :json-ld="$jsonLd"
>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">The calendar</p>
                <h1>What's on, and where</h1>
                <p>Upcoming matches on a month grid. Planned dates stay provisional; confirmed dates look different on the match page.</p>
                <div class="view-toggle" role="group" aria-label="Calendar view">
                    <a href="{{ route('calendar', request()->except('month')) }}" aria-pressed="false">List</a>
                    <a href="{{ route('calendar.month', request()->query()) }}" aria-pressed="true">Month</a>
                    <a href="{{ route('map') }}" aria-pressed="false">Map</a>
                </div>
            </div>
        </section>
        <section class="block">
            <div class="wrap">
                <livewire:calendar-month
                    :family="$family"
                    :novice="$novice"
                    :confirmed="$confirmed"
                    :discipline="$discipline"
                    :province="$province"
                    :radius="$radius"
                    :near="$near ?? null"
                    :lat="$lat ?? null"
                    :lng="$lng ?? null"
                    :from="$from"
                    :to="$to"
                    :month="$month"
                />
                <x-ad-slot page="calendar" placement-slot="leaderboard" :limit="2" class="ad-rail--tight" hide-when-vacant />
            </div>
        </section>
    </main>
</x-layouts.public>
