<div class="console">
    <form wire:submit="search">
        <div class="console-head">Match finder <span>{{ $indexed }} disciplines indexed</span></div>
        <div class="console-grid">
            <label class="field">
                <span>Discipline</span>
                <select wire:model="discipline">
                    <option value="">All disciplines</option>
                    @foreach ($disciplines as $item)
                        <option value="{{ $item->slug }}">{{ $item->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span>Province</span>
                <select wire:model="province">
                    <option value="">All provinces</option>
                    @foreach ($provinces as $item)
                        <option value="{{ $item->urlSlug() }}">{{ $item->getLabel() }}</option>
                    @endforeach
                </select>
            </label>
            {{-- Radius dropdown removed (UX audit #1) — see MatchFinder
                 PHP for why. Do not re-add without venue coordinates
                 and a working Haversine scope on PublicEventQuery. --}}
        </div>
        <div class="console-grid" style="margin-top:10px">
            <label class="field">
                <span>From</span>
                <input type="date" wire:model="from">
            </label>
            <label class="field">
                <span>To</span>
                <input type="date" wire:model="to">
            </label>
        </div>
        <button class="btn" type="submit">Search the calendar</button>
    </form>
</div>
