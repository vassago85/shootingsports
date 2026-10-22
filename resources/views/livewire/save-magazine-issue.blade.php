<button
    type="button"
    class="btn ghost magazine-control magazine-control--save"
    wire:click="toggle"
    wire:loading.attr="disabled"
    aria-pressed="{{ $saved ? 'true' : 'false' }}"
>
    <span wire:loading.remove>{{ $saved ? 'Saved' : 'Save to my library' }}</span>
    <span wire:loading>Saving…</span>
</button>
