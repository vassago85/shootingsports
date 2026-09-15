<div class="log-attendance">
    @if ($logged)
        <button type="button" class="btn ghost" wire:click="unlog" title="Remove this match from your log">
            ✓ Logged &middot; remove
        </button>
    @else
        <button type="button" class="btn ghost" wire:click="openForm">
            I shot this
        </button>
    @endif

    @if ($open)
        <div class="log-attendance-modal" role="dialog" aria-modal="true" aria-labelledby="log-attendance-title">
            <div class="log-attendance-backdrop" wire:click="cancel" aria-hidden="true"></div>
            <div class="log-attendance-panel">
                <button type="button" class="log-attendance-close" wire:click="cancel" aria-label="Close">&times;</button>

                <p class="label">Log this match</p>
                <h2 id="log-attendance-title">What did you shoot?</h2>
                <p style="color:var(--slate);font-size:14px;margin:0 0 12px">
                    All fields optional. You can edit or add these later from your log.
                </p>

                <form wire:submit.prevent="save" class="log-attendance-form">
                    <div class="log-attendance-grid">
                        <label class="field">
                            <span>Division</span>
                            <input type="text" wire:model.defer="division" placeholder="e.g. Standard, Production, Optics">
                            @error('division') <em class="err">{{ $message }}</em> @enderror
                        </label>
                        <label class="field">
                            <span>Class / grade</span>
                            <input type="text" wire:model.defer="classification" placeholder="e.g. A, B, GM, M">
                            @error('classification') <em class="err">{{ $message }}</em> @enderror
                        </label>
                        <label class="field">
                            <span>Placing</span>
                            <input type="number" min="1" wire:model.defer="placing" placeholder="e.g. 3">
                            @error('placing') <em class="err">{{ $message }}</em> @enderror
                        </label>
                        <label class="field">
                            <span>Field size</span>
                            <input type="number" min="1" wire:model.defer="field_size" placeholder="e.g. 24">
                            @error('field_size') <em class="err">{{ $message }}</em> @enderror
                        </label>
                        <label class="field" style="grid-column:1 / -1">
                            <span>Score</span>
                            <input type="text" wire:model.defer="score" placeholder="e.g. 82.4% or 445/500">
                            @error('score') <em class="err">{{ $message }}</em> @enderror
                        </label>
                        <label class="field" style="grid-column:1 / -1">
                            <span>Notes</span>
                            <textarea rows="3" wire:model.defer="notes" placeholder="Ammo, wind, lessons learned…"></textarea>
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
