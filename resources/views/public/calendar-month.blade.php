<x-layouts.public
    :title="$seoTitle"
    :description="$seoDescription"
    :canonical="$canonical"
    :json-ld="$jsonLd"
>
    <main id="main">
        <div class="wrap dir-page">
            <x-dir-hero page="calendar" kicker="Matches" title="Calendar">
                The same matches as the list, laid out by month. The list is the main view.
            </x-dir-hero>
        </div>
        <section class="match-board">
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
                <x-ad-slot page="calendar" placement-slot="leaderboard" :limit="2" class="ss-partner--tight" hide-when-vacant />
            </div>
        </section>
    </main>
</x-layouts.public>
