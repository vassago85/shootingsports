<button
    type="button"
    class="{{ $variant === 'button' ? 'btn ghost' : 'dope-save' }}"
    wire:click="toggle"
    aria-pressed="{{ $saved ? 'true' : 'false' }}"
>
    {{ $saved ? 'On my calendar' : 'Add to my calendar' }}
</button>
