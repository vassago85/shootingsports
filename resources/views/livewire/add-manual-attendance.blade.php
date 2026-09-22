<div>
    <button type="button" class="btn ghost" wire:click="openForm">
        + Add a match
    </button>

    @if ($open)
        <div class="log-attendance-modal" role="dialog" aria-modal="true" aria-labelledby="add-manual-title">
            <div class="log-attendance-backdrop" wire:click="cancel" aria-hidden="true"></div>
            <div class="log-attendance-panel">
                <button type="button" class="log-attendance-close" wire:click="cancel" aria-label="Close">&times;</button>

                <p class="label">Add to your log</p>
                <h2 id="add-manual-title">Log a match manually</h2>
                <p style="color:var(--slate);font-size:14px;margin:0 0 12px">
                    For matches not in our calendar. Historical events, out-of-country matches, informal club shoots.
                </p>

                <form wire:submit.prevent="save" class="log-attendance-form">
                    <div class="log-attendance-grid">
                        <label class="field" style="grid-column:1 / -1">
                            <span>Match name *</span>
                            <input type="text" wire:model.defer="event_name" required placeholder="e.g. Pretoria PRC Club Shoot">
                            @error('event_name') <em class="err">{{ $message }}</em> @enderror
                        </label>
                        <label class="field">
                            <span>Date *</span>
                            <input type="date" wire:model.defer="event_date" required>
                            @error('event_date') <em class="err">{{ $message }}</em> @enderror
                        </label>
                        <label class="field">
                            <span>Discipline</span>
                            <select wire:model.defer="discipline_id">
                                <option value="">Pick one</option>
                                @foreach ($disciplines as $d)
                                    <option value="{{ $d->id }}">{{ $d->name }}</option>
                                @endforeach
                            </select>
                            @error('discipline_id') <em class="err">{{ $message }}</em> @enderror
                        </label>
                        <label class="field">
                            <span>Host / club</span>
                            <input type="text" wire:model.defer="host" placeholder="e.g. Pretoria RC">
                            @error('host') <em class="err">{{ $message }}</em> @enderror
                        </label>
                        <label class="field">
                            <span>Venue / range</span>
                            <input type="text" wire:model.defer="venue" placeholder="e.g. Pretoria Range">
                            @error('venue') <em class="err">{{ $message }}</em> @enderror
                        </label>
                        <label class="field">
                            <span>Division</span>
                            <input type="text" wire:model.defer="division" placeholder="e.g. Production">
                            @error('division') <em class="err">{{ $message }}</em> @enderror
                        </label>
                        <label class="field">
                            <span>Class</span>
                            <input type="text" wire:model.defer="classification" placeholder="e.g. B">
                            @error('classification') <em class="err">{{ $message }}</em> @enderror
                        </label>
                        <label class="field">
                            <span>Placing</span>
                            <input type="number" min="1" wire:model.defer="placing">
                            @error('placing') <em class="err">{{ $message }}</em> @enderror
                        </label>
                        <label class="field">
                            <span>Field size</span>
                            <input type="number" min="1" wire:model.defer="field_size">
                            @error('field_size') <em class="err">{{ $message }}</em> @enderror
                        </label>
                        <label class="field" style="grid-column:1 / -1">
                            <span>Score</span>
                            <input type="text" wire:model.defer="score" placeholder="e.g. 82.4% or 445/500">
                            @error('score') <em class="err">{{ $message }}</em> @enderror
                        </label>
                        <label class="field" style="grid-column:1 / -1">
                            <span>Notes</span>
                            <textarea rows="3" wire:model.defer="notes"></textarea>
                            @error('notes') <em class="err">{{ $message }}</em> @enderror
                        </label>
                    </div>

                    <div class="log-attendance-actions">
                        <button type="submit" class="btn" wire:loading.attr="disabled" wire:target="save">
                            <span wire:loading.remove wire:target="save">Save to my log</span>
                            <span wire:loading wire:target="save">Saving…</span>
                        </button>
                        <button type="button" class="btn ghost" wire:click="cancel">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
