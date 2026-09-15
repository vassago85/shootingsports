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

    @if ($events->isEmpty())
        <p class="empty">No matches in this window. Planned dates still appear on the calendar — they render as provisional.</p>
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
