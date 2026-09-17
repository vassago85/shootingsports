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

/*
 * Homepage "Near me" quick action: request geolocation, then open
 * the matches list with lat/lng/radius so the visitor does not have
 * to discover the distance filter first. Falls back to plain
 * /calendar when geolocation is denied or unavailable.
 */
document.addEventListener('click', (event) => {
    const link = event.target.closest('[data-near-me]');

    if (! link) {
        return;
    }

    if (! navigator.geolocation) {
        return;
    }

    event.preventDefault();
    link.setAttribute('aria-busy', 'true');

    navigator.geolocation.getCurrentPosition(
        (pos) => {
            const url = new URL(link.href, window.location.origin);
            url.searchParams.set('lat', String(pos.coords.latitude));
            url.searchParams.set('lng', String(pos.coords.longitude));
            url.searchParams.set('radius', '150');
            window.location.assign(url.toString());
        },
        () => {
            window.location.assign(link.href);
        },
        { enableHighAccuracy: false, timeout: 10000 },
    );
});
