{{-- Livewire requires a single root tag on the component view.
     Wrap the auth split so both branches sit inside one <div>. --}}
<div class="follow-btn-wrap">
    @auth
        <button
            type="button"
            class="btn ghost follow-btn"
            wire:click="toggle"
            aria-pressed="{{ $following ? 'true' : 'false' }}"
            data-follow-type="{{ $followableType }}"
        >
            {{ $following ? 'Following' : $label }}
        </button>
    @else
        {{-- Contextual auth panel instead of an unexplained redirect
             to /login. Same visible button label as the authed
             control so the page does not shift under the cursor. --}}
        <x-auth-gate action="follow" :label="$label" />
    @endauth
</div>
