<button
    type="button"
    class="{{ $variant === 'button' ? 'btn ghost' : 'dope-save' }}"
    wire:click="toggle"
    aria-pressed="{{ $saved ? 'true' : 'false' }}"
>
    {{ $saved ? 'Saved to my account' : 'Save to my Shooting Sports account' }}
</button>
