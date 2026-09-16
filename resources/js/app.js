document.addEventListener('click', (event) => {
    const burger = event.target.closest('#burger');

    if (! burger) {
        return;
    }

    const menu = document.getElementById('mobile-menu');

    if (! menu) {
        return;
    }

    const open = menu.classList.toggle('open');
    burger.setAttribute('aria-expanded', open ? 'true' : 'false');
});

/*
 * UX audit #3 — Skeleton-safe filter chips.
 *
 * Livewire filter chips render server-side and look interactive
 * before the component hydrates. The first click on a family or
 * province chip was being swallowed because the wire:click handler
 * wasn't bound yet — user's audit called this out directly.
 *
 * CSS keeps `.filters` at reduced opacity + pointer-events:none by
 * default. Once Livewire fires `livewire:init` we add `.is-hydrated`
 * so the chips become interactive. Also add it to any `.filters`
 * added by later Livewire re-renders via a MutationObserver.
 */
const hydrateFilters = (root = document) => {
    root.querySelectorAll('.filters:not(.is-hydrated)').forEach((el) => {
        el.classList.add('is-hydrated');
    });
};

document.addEventListener('livewire:init', () => {
    hydrateFilters();
});

if (document.readyState === 'complete' || document.readyState === 'interactive') {
    // Livewire may already have booted before this script parses on
    // fast reloads — hydrate immediately in that case.
    hydrateFilters();
} else {
    document.addEventListener('DOMContentLoaded', () => hydrateFilters());
}

// Livewire re-renders replace the DOM subtree; re-mark any new
// `.filters` blocks after each morph.
document.addEventListener('livewire:navigated', () => hydrateFilters());
document.addEventListener('livewire:morph.updated', () => hydrateFilters());
