<button
    type="button"
    class="btn ghost follow-btn"
    wire:click="toggle"
    aria-pressed="{{ $following ? 'true' : 'false' }}"
    data-follow-type="{{ $followableType }}"
>
    {{ $following ? 'Following' : $label }}
</button>
