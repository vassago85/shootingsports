<div>
    <div class="filters" role="group" aria-label="Filter matches by discipline family">
        <button type="button" wire:click="setFamily('all')" aria-pressed="{{ $family === 'all' ? 'true' : 'false' }}">All</button>
        @foreach ($families as $item)
            <button type="button" wire:click="setFamily('{{ $item->value }}')" aria-pressed="{{ $family === $item->value ? 'true' : 'false' }}">{{ $item->getLabel() }}</button>
        @endforeach
    </div>
    <div class="filters" role="group" aria-label="Additional filters" style="margin-top:-14px">
        <button type="button" wire:click="toggleNovice" aria-pressed="{{ $novice ? 'true' : 'false' }}">New shooter friendly</button>
        <button type="button" wire:click="toggleConfirmed" aria-pressed="{{ $confirmed ? 'true' : 'false' }}">Confirmed dates only</button>
    </div>
    <div class="filters" role="group" aria-label="Filter matches by province" style="margin-top:-14px">
        <button type="button" wire:click="clearProvinces" aria-pressed="{{ $selectedProvinces === [] ? 'true' : 'false' }}">All provinces</button>
        @foreach ($provinces as $item)
            <button
                type="button"
                wire:click="toggleProvince('{{ $item->urlSlug() }}')"
                aria-pressed="{{ in_array($item->urlSlug(), $selectedProvinces, true) ? 'true' : 'false' }}"
                title="{{ $item->getLabel() }}"
            >{{ $item->getLabel() }}</button>
        @endforeach
    </div>

    @if (filled($activeDisciplineLabel) || filled($from) || filled($to) || filled($near) || filled($lat) || filled($radius))
        <div class="active-filters" role="group" aria-label="Active filters" style="margin-top:-14px">
            <span class="active-filters-label">Filtered:</span>
            @if (filled($activeDisciplineLabel))
                <button type="button" class="chip-active" wire:click="clearDiscipline">
                    <span>{{ $activeDisciplineLabel }}</span>
                    <span aria-hidden="true">×</span>
                </button>
            @endif
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
        </div>
    @endif

    <div class="filters distance-filters" role="group" aria-label="Distance filter" style="margin-top:-14px" x-data="calendarNearGeo()">
        <label class="distance-near">
            <span class="sr-only">Near town</span>
            <input type="text" wire:model.live.debounce.400ms="near" placeholder="Near town…" aria-label="Near town">
        </label>
        <select wire:model.live="radius" aria-label="Within distance">
            <option value="">Any distance</option>
            @foreach ($radii as $km)
                <option value="{{ $km }}">{{ $km }} km</option>
            @endforeach
        </select>
        <button type="button" class="btn ghost" style="padding:8px 12px" @click="locate" :disabled="locating">
            <span x-text="locating ? 'Locating…' : 'Use my location'"></span>
        </button>
    </div>

    <div class="month-nav">
        <button type="button" class="btn ghost" wire:click="previousMonth" aria-label="Previous month">←</button>
        <h2 class="month-nav-label">{{ $monthLabel }}</h2>
        <div class="month-nav-actions">
            <button type="button" class="btn ghost" wire:click="goToday">Today</button>
            <button type="button" class="btn ghost" wire:click="nextMonth" aria-label="Next month">→</button>
        </div>
    </div>

    <div class="result-bar">
        <p class="result-count">
            {{ $resultCount }} upcoming {{ $resultCount === 1 ? 'match' : 'matches' }} this month
            @if ($hasActiveFilters)
                <span class="result-count-suffix">— filtered</span>
            @endif
        </p>
        @if ($hasActiveFilters)
            <button type="button" class="btn-clear-filters" wire:click="clearAll">Clear filters</button>
        @endif
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
