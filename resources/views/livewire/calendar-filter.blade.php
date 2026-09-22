@php
    $isTeaser = (bool) $showMore;
    $moreFiltersOpen = filled($near ?? null) || filled($lat ?? null) || filled($radius ?? null) || filled($from ?? null) || filled($to ?? null);
@endphp

<div
    class="match-board {{ $isTeaser ? 'is-teaser' : '' }}"
    x-data="{ moreOpen: {{ $moreFiltersOpen ? 'true' : 'false' }} }"
>
    @unless ($isTeaser)
        {{--
            Compact sticky toolbar: title / count on the left, primary chips
            underneath, view toggle on the right. Advanced filters live in the
            #mb-more panel toggled by the More filters chip. Every filter still
            binds to the same #[Url] Livewire properties, so URLs, bookmarks and
            the existing test surface are unchanged.
        --}}
        <div class="mb-toolbar">
            <div class="mb-toolbar-in">
                <h2 class="mb-toolbar-title">
                    Matches
                    <small>
                        {{ $resultCount }} {{ $resultCount === 1 ? 'match' : 'matches' }}
                        @if ($hasActiveFilters)
                            <span class="result-count-suffix">— filtered</span>
                        @endif
                    </small>
                    @if ($hasActiveFilters)
                        <button type="button" class="btn-clear-filters mb-toolbar-clear" wire:click="clearAll">Clear all</button>
                    @endif
                </h2>
                <div class="view-toggle" role="group" aria-label="Matches view">
                    <a href="{{ route('calendar', request()->query()) }}" aria-pressed="true">List</a>
                    <a href="{{ route('calendar.month', request()->query()) }}" aria-pressed="false">Month</a>
                    <a href="{{ route('map', request()->except(['month'])) }}" aria-pressed="false">Map</a>
                </div>

                <div class="mb-toolbar-chips filters" role="group" aria-label="Primary match filters">
                    <button type="button" class="mb-chip" wire:click="toggleWeekend" aria-pressed="{{ $weekend ? 'true' : 'false' }}">This weekend</button>
                    <button type="button" class="mb-chip is-default" wire:click="setFamily('all')" aria-pressed="{{ $family === 'all' ? 'true' : 'false' }}">All disciplines</button>
                    @foreach ($families as $item)
                        <button type="button" class="mb-chip" wire:click="setFamily('{{ $item->value }}')" aria-pressed="{{ $family === $item->value ? 'true' : 'false' }}">{{ $item->getLabel() }}</button>
                    @endforeach
                    <button type="button" class="mb-chip" wire:click="toggleNovice" aria-pressed="{{ $novice ? 'true' : 'false' }}">New shooter</button>
                    <button
                        type="button"
                        class="mb-chip"
                        @click="moreOpen = ! moreOpen"
                        :aria-expanded="moreOpen ? 'true' : 'false'"
                        aria-controls="mb-more-panel"
                    >More filters</button>
                </div>
            </div>

            {{--
                Active chips for every applied filter — including ones that live
                behind More filters (province, confirmed, distance, dates).
                Sticky with the toolbar so a filter is always one × away from gone.
            --}}
            @if ($hasActiveFilters)
                <div class="mb-active active-filters" role="group" aria-label="Active filters">
                    <span class="mb-active-label active-filters-label">Filtered:</span>
                    @if ($weekend)
                        <button type="button" class="chip-active" wire:click="clearWeekend">
                            <span>This weekend</span>
                            <span aria-hidden="true">×</span>
                            <span class="sr-only">— clear this weekend filter</span>
                        </button>
                    @endif
                    @if ($family !== 'all')
                        <button type="button" class="chip-active" wire:click="clearFamily">
                            <span>{{ \App\Enums\Division::tryFrom($family)?->getLabel() ?? \App\Enums\DisciplineFamily::tryFrom($family)?->getLabel() ?? ucfirst($family) }}</span>
                            <span aria-hidden="true">×</span>
                            <span class="sr-only">— clear discipline family filter</span>
                        </button>
                    @endif
                    @if ($novice)
                        <button type="button" class="chip-active" wire:click="clearNovice">
                            <span>New shooter</span>
                            <span aria-hidden="true">×</span>
                            <span class="sr-only">— clear new shooter filter</span>
                        </button>
                    @endif
                    @if ($confirmed)
                        <button type="button" class="chip-active" wire:click="clearConfirmed">
                            <span>Confirmed only</span>
                            <span aria-hidden="true">×</span>
                            <span class="sr-only">— clear confirmed dates filter</span>
                        </button>
                    @endif
                    @if (filled($activeDisciplineLabel))
                        <button type="button" class="chip-active" wire:click="clearDiscipline">
                            <span>{{ $activeDisciplineLabel }}</span>
                            <span aria-hidden="true">×</span>
                            <span class="sr-only">— clear discipline filter</span>
                        </button>
                    @endif
                    @foreach ($selectedProvinces as $slug)
                        @php $provinceLabel = \App\Enums\Province::fromUrlSlug($slug)?->getLabel() ?? $slug; @endphp
                        <button type="button" class="chip-active" wire:click="toggleProvince('{{ $slug }}')">
                            <span>{{ $provinceLabel }}</span>
                            <span aria-hidden="true">×</span>
                            <span class="sr-only">— clear {{ $provinceLabel }} filter</span>
                        </button>
                    @endforeach
                    @if ((! $weekend) && (filled($from) || filled($to)))
                        <button type="button" class="chip-active" wire:click="clearDates">
                            <span>{{ $from ?: '…' }} → {{ $to ?: '…' }}</span>
                            <span aria-hidden="true">×</span>
                            <span class="sr-only">— clear date range filter</span>
                        </button>
                    @endif
                    @if (filled($near) || filled($lat) || filled($radius))
                        <button type="button" class="chip-active" wire:click="clearDistance">
                            <span>
                                @if (filled($near))
                                    Within {{ $radius ?: '…' }} km of {{ $near }}
                                @elseif (filled($lat))
                                    Within {{ $radius ?: '…' }} km of my location
                                @else
                                    Within {{ $radius }} km
                                @endif
                            </span>
                            <span aria-hidden="true">×</span>
                            <span class="sr-only">— clear distance filter</span>
                        </button>
                    @endif
                    <button type="button" class="btn-clear-filters" wire:click="clearAll">Clear all</button>
                </div>
            @endif
        </div>

        {{-- Advanced filters panel (progressive disclosure).
             Every control here still writes to the same Livewire property. --}}
        <div
            id="mb-more-panel"
            class="mb-more"
            x-show="moreOpen"
            x-cloak
            role="group"
            aria-label="Advanced filters"
        >
            <label class="field">
                <span>Near town</span>
                <input type="text" wire:model.live.debounce.400ms="near" placeholder="e.g. Centurion" autocomplete="address-level2">
            </label>
            <label class="field">
                <span>Within</span>
                <select wire:model.live="radius">
                    <option value="">Any distance</option>
                    @foreach ($radii as $km)
                        <option value="{{ $km }}">{{ $km }} km</option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span>From</span>
                <input type="date" wire:model.live="from">
            </label>
            <label class="field">
                <span>To</span>
                <input type="date" wire:model.live="to">
            </label>

            <div class="mb-more-toggles" x-data="calendarNearGeo()">
                <button type="button" @click="locate" :disabled="locating">
                    <span x-text="locating ? 'Locating…' : 'Use my location'"></span>
                </button>
                <button type="button" wire:click="toggleConfirmed" aria-pressed="{{ $confirmed ? 'true' : 'false' }}">Confirmed dates only</button>
                @auth
                    <button
                        type="button"
                        class="btn ghost"
                        wire:click="saveSearch"
                        x-data="{ saved: false }"
                        x-on:search-saved.window="saved = true; setTimeout(() => saved = false, 3000)"
                        x-text="saved ? 'Saved' : 'Save this search'"
                    >Save this search</button>
                @endauth
            </div>

            <div class="mb-more-toggles filters" role="group" aria-label="Filter matches by province">
                <button type="button" class="mb-chip is-default" wire:click="clearProvinces" aria-pressed="{{ $selectedProvinces === [] ? 'true' : 'false' }}">All provinces</button>
                @foreach ($provinces as $item)
                    <button
                        type="button"
                        class="mb-chip"
                        wire:click="toggleProvince('{{ $item->urlSlug() }}')"
                        aria-pressed="{{ in_array($item->urlSlug(), $selectedProvinces, true) ? 'true' : 'false' }}"
                        title="{{ $item->getLabel() }}"
                    >{{ $item->getLabel() }}</button>
                @endforeach
            </div>
        </div>

        @if (($unpinnedSkipped ?? 0) > 0)
            <p class="mb-count">
                <b>{{ $resultCount }}</b> {{ $resultCount === 1 ? 'match' : 'matches' }}
                <span class="result-count-suffix">· {{ $unpinnedSkipped }} without a map pin yet</span>
            </p>
        @endif
    @endunless

    {{--
        The match board itself. Grouped into "This weekend" / "Next weekend"
        / month buckets so the calendar reads like a national programme
        instead of a flat product grid. Empty state is dark board styling
        with recovery CTAs — same wording as the previous implementation so
        the ThisWeekendFilterTest strings remain stable.
    --}}
    @if ($events->isEmpty())
        <div class="mb-empty">
            <h3>No matches found</h3>
            @if ($weekend)
                <p class="empty">Nothing listed for this weekend ({{ $weekendLabel }}). Planned dates still appear when clubs publish them — try next month or clear the filter.</p>
            @else
                <p class="empty">No matches in this window. Planned dates still appear on the calendar — they render as provisional.</p>
            @endif
            @if ($hasActiveFilters)
                <div class="mb-empty-actions empty-actions">
                    <button type="button" class="btn ghost" wire:click="clearAll">Clear filters</button>
                    @if ($weekend)
                        <button type="button" class="btn ghost" wire:click="toggleWeekend">Show all upcoming</button>
                    @elseif (filled($from) || filled($to))
                        <button type="button" class="btn ghost" wire:click="clearDates">Widen the date range</button>
                    @endif
                </div>
            @endif
        </div>
    @else
        @foreach ($grouped as $group)
            <section class="mb-section" aria-labelledby="mb-section-{{ $loop->index }}">
                <div class="mb-section-head">
                    <h3 id="mb-section-{{ $loop->index }}">{{ $group['label'] }}</h3>
                    <span class="mb-section-count">{{ $group['events']->count() }} {{ $group['events']->count() === 1 ? 'match' : 'matches' }}</span>
                </div>
                <div class="mb-list">
                    @foreach ($group['events'] as $event)
                        <x-match-row :event="$event" />
                    @endforeach
                </div>
            </section>
        @endforeach
    @endif

    @if ($showMore)
        <a class="mb-more-link" href="{{ route('calendar') }}">All matches →</a>
    @endif
</div>

<script>
    function calendarNearGeo() {
        return {
            locating: false,
            locate() {
                if (! navigator.geolocation) return;
                this.locating = true;
                navigator.geolocation.getCurrentPosition(
                    (pos) => {
                        this.locating = false;
                        @this.set('lat', String(pos.coords.latitude));
                        @this.set('lng', String(pos.coords.longitude));
                        @this.set('near', '');
                        if (! @this.get('radius')) {
                            @this.set('radius', '150');
                        }
                    },
                    () => { this.locating = false; },
                    { enableHighAccuracy: false, timeout: 10000 }
                );
            }
        };
    }
</script>
