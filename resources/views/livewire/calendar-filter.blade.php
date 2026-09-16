<div>
    {{--
        Family row. `.filters` gets `.is-hydrated` from app.js once
        Livewire boots — until then CSS keeps the buttons at reduced
        opacity + pointer-events:none so the first click is not eaten
        by an un-hydrated component (UX audit #3).
    --}}
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

    {{--
        Active-filter chips row (UX audit #2). Renders visible chips
        for filters that arrive via URL params from the hero MatchFinder
        and are NOT already shown as pressed buttons above — discipline
        and date range. Each chip has its own × to clear that single
        filter. Family / province / novice / confirmed are already
        reflected via aria-pressed on the button rows above.
    --}}
    @if (filled($activeDisciplineLabel) || filled($from) || filled($to))
        <div class="active-filters" role="group" aria-label="Active filters" style="margin-top:-14px">
            <span class="active-filters-label">Filtered:</span>
            @if (filled($activeDisciplineLabel))
                <button type="button" class="chip-active" wire:click="clearDiscipline">
                    <span>{{ $activeDisciplineLabel }}</span>
                    <span aria-hidden="true">×</span>
                    <span class="sr-only">— clear discipline filter</span>
                </button>
            @endif
            @if (filled($from) || filled($to))
                <button type="button" class="chip-active" wire:click="clearDates">
                    <span>{{ $from ?: '…' }} → {{ $to ?: '…' }}</span>
                    <span aria-hidden="true">×</span>
                    <span class="sr-only">— clear date range filter</span>
                </button>
            @endif
        </div>
    @endif

    @auth
        {{-- Save-this-search sits next to the filter chips. Free users
             may save one; the second attempt fires the UpgradePrompt
             via the observer, not a toast. --}}
        <div class="filters" role="group" aria-label="Save this filter set" style="margin-top:-14px">
            <button
                type="button"
                class="btn ghost"
                wire:click="saveSearch"
                x-data="{ saved: false }"
                x-on:search-saved.window="saved = true; setTimeout(() => saved = false, 3000)"
                x-text="saved ? 'Saved' : 'Save this search'"
            >Save this search</button>
        </div>
    @endauth

    {{--
        Result-count bar (UX audit #14). Always visible above the grid
        so the visitor knows the filter actually did something. The
        Clear filters button only renders when a filter has been
        applied — hides completely on the default calendar view so it
        doesn't beg to be clicked.
    --}}
    <div class="result-bar">
        <p class="result-count">
            {{ $resultCount }} {{ $resultCount === 1 ? 'match' : 'matches' }}
            @if ($hasActiveFilters)
                <span class="result-count-suffix">— filtered</span>
            @endif
        </p>
        @if ($hasActiveFilters)
            <button type="button" class="btn-clear-filters" wire:click="clearAll">Clear filters</button>
        @endif
    </div>

    @if ($events->isEmpty())
        <div class="empty-state">
            <p class="empty">No matches in this window. Planned dates still appear on the calendar — they render as provisional.</p>
            @if ($hasActiveFilters)
                <div class="empty-actions">
                    <button type="button" class="btn ghost" wire:click="clearAll">Clear filters</button>
                    @if (filled($from) || filled($to))
                        <button type="button" class="btn ghost" wire:click="clearDates">Widen the date range</button>
                    @endif
                </div>
            @endif
        </div>
    @else
        <div class="dope-grid">
            @foreach ($events as $event)
                <x-event-card :event="$event" />
            @endforeach
        </div>
    @endif

    @if ($showMore)
        <p style="margin-top:22px">
            <a class="label" href="{{ route('calendar') }}" style="text-decoration:none;border-bottom:1px solid var(--brass)">Full calendar →</a>
        </p>
    @endif
</div>
