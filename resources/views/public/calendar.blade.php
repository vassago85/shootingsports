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
            </div>
        </section>
    </main>
</x-layouts.public>
