<x-layouts.public
    :title="$seoTitle"
    :description="$seoDescription"
    :canonical="$canonical"
    :json-ld="$jsonLd"
>
    <main id="main">
        <div class="wrap dir-page">
            <x-dir-hero page="matches" kicker="Matches" title="Find a match">
                Discover shooting matches across South Africa. The list is the main view. Calendar and map are other ways to explore.
            </x-dir-hero>

            @if (auth()->user()?->is_staff && filled(config('product-backlog.items')))
                <section class="build-list" aria-labelledby="build-list-heading">
                    <h2 id="build-list-heading" class="label">Still to build</h2>
                    <ul>
                        @foreach (config('product-backlog.items') as $item)
                            <li>
                                <span>{{ $item['title'] }}</span>
                                @if (($item['audience'] ?? null) === 'pro')
                                    <span class="build-list-plan">Pro</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>
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
                <x-ad-slot page="calendar" placement-slot="leaderboard" :limit="2" class="ss-partner--tight" hide-when-vacant />
            </div>
        </section>
    </main>
</x-layouts.public>
