@php
    $moreFiltersOpen = filled($near ?? null) || filled($lat ?? null) || filled($radius ?? null) || filled($from ?? null) || filled($to ?? null) || filled($province ?? null) || $confirmed;
@endphp

<div x-data="{ moreOpen: {{ $moreFiltersOpen ? 'true' : 'false' }} }">
    <div class="mb-toolbar">
        <div class="mb-toolbar-in">
            <h2 class="mb-toolbar-title">
                Matches
                <small>
                    {{ $resultCount }} upcoming {{ $resultCount === 1 ? 'match' : 'matches' }} this month
                    @if ($hasActiveFilters)
                        <span class="result-count-suffix">— filtered</span>
                    @endif
                </small>
                @if ($hasActiveFilters)
                    <button type="button" class="btn-clear-filters mb-toolbar-clear" wire:click="clearAll">Clear all</button>
                @endif
            </h2>
            <div class="view-toggle" role="group" aria-label="Matches view">
                <a href="{{ route('calendar', request()->except('month')) }}" aria-pressed="false">List</a>
                <a href="{{ route('calendar.month', request()->query()) }}" aria-pressed="true">Month</a>
                <a href="{{ route('map', request()->except(['month'])) }}" aria-pressed="false">Map</a>
            </div>

            <div class="mb-toolbar-chips filters" role="group" aria-label="Primary match filters">
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
                    aria-controls="mb-month-more-panel"
                >More filters</button>
            </div>
        </div>

        @if ($hasActiveFilters)
            <div class="mb-active active-filters" role="group" aria-label="Active filters">
                <span class="mb-active-label active-filters-label">Filtered:</span>
                @if ($family !== 'all')
                    <button type="button" class="chip-active" wire:click="clearFamily">
                        <span>{{ \App\Enums\DisciplineFamily::tryFrom($family)?->getLabel() ?? ucfirst($family) }}</span>
                        <span aria-hidden="true">×</span>
                    </button>
                @endif
                @if ($novice)
                    <button type="button" class="chip-active" wire:click="clearNovice">
                        <span>New shooter</span>
                        <span aria-hidden="true">×</span>
                    </button>
                @endif
                @if ($confirmed)
                    <button type="button" class="chip-active" wire:click="clearConfirmed">
                        <span>Confirmed only</span>
                        <span aria-hidden="true">×</span>
                    </button>
                @endif
                @if (filled($activeDisciplineLabel))
                    <button type="button" class="chip-active" wire:click="clearDiscipline">
                        <span>{{ $activeDisciplineLabel }}</span>
                        <span aria-hidden="true">×</span>
                    </button>
                @endif
                @foreach ($selectedProvinces as $slug)
                    @php $provinceLabel = \App\Enums\Province::fromUrlSlug($slug)?->getLabel() ?? $slug; @endphp
                    <button type="button" class="chip-active" wire:click="toggleProvince('{{ $slug }}')">
                        <span>{{ $provinceLabel }}</span>
                        <span aria-hidden="true">×</span>
                    </button>
                @endforeach
                @if (filled($from) || filled($to))
                    <button type="button" class="chip-active" wire:click="clearDates">
                        <span>{{ $from ?: '…' }} → {{ $to ?: '…' }}</span>
                        <span aria-hidden="true">×</span>
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
                    </button>
                @endif
                <button type="button" class="btn-clear-filters" wire:click="clearAll">Clear all</button>
            </div>
        @endif
    </div>

    <div
        id="mb-month-more-panel"
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

    <div class="month-nav">
        <button type="button" class="btn ghost" wire:click="previousMonth" aria-label="Previous month">←</button>
        <h2 class="month-nav-label">{{ $monthLabel }}</h2>
        <div class="month-nav-actions">
            <button type="button" class="btn ghost" wire:click="goToday">Today</button>
            <button type="button" class="btn ghost" wire:click="nextMonth" aria-label="Next month">→</button>
        </div>
    </div>

    <div class="month-cal" role="grid" aria-label="{{ $monthLabel }} match calendar">
        <div class="month-cal-head" role="row">
            @foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $dow)
                <div role="columnheader">{{ $dow }}</div>
            @endforeach
        </div>
        <div class="month-cal-body">
            @foreach ($days as $day)
                <div
                    class="month-cal-day {{ $day['inMonth'] ? '' : 'is-outside' }} {{ $day['isToday'] ? 'is-today' : '' }}"
                    role="gridcell"
                    aria-label="{{ $day['date']->format('j F Y') }}"
                >
                    <span class="month-cal-num">{{ $day['date']->format('j') }}</span>
                    @php
                        $dayEvents = $day['events'];
                        $shown = $dayEvents->take(3);
                        $more = $dayEvents->count() - $shown->count();
                    @endphp
                    <ul class="month-cal-chips">
                        @foreach ($shown as $event)
                            <li>
                                <a href="{{ route('matches.show', $event->slug) }}" title="{{ $event->title }}">
                                    <span class="chip-title">{{ \Illuminate\Support\Str::limit($event->title, 28) }}</span>
                                    @if ($event->venue?->town)
                                        <span class="chip-town">{{ $event->venue->town }}</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                        @if ($more > 0)
                            <li class="month-cal-more">
                                <a href="{{ route('calendar', ['from' => $day['key'], 'to' => $day['key']]) }}">+{{ $more }} more</a>
                            </li>
                        @endif
                    </ul>
                </div>
            @endforeach
        </div>
    </div>
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
