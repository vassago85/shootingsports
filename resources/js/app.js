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
 * so the chips become interactive.
 *
 * Critical: every Livewire morph replaces the chip row WITHOUT the
 * is-hydrated class, which used to leave pointer-events:none in place
 * and made a second filter click silently fail ("you can only apply
 * one filter"). Re-hydrate after morphs via Livewire hooks AND a
 * MutationObserver so we don't depend on a single event name.
 */
const hydrateFilters = (root = document) => {
    root.querySelectorAll('.filters:not(.is-hydrated)').forEach((el) => {
        el.classList.add('is-hydrated');
    });
};

const bindFilterHydration = () => {
    hydrateFilters();

    if (window.Livewire && typeof window.Livewire.hook === 'function') {
        window.Livewire.hook('morph.updated', ({ el }) => {
            hydrateFilters(el instanceof Element ? el : document);
        });
        window.Livewire.hook('commit', ({ succeed }) => {
            succeed(() => hydrateFilters());
        });
    }
};

document.addEventListener('livewire:init', bindFilterHydration);

if (document.readyState === 'complete' || document.readyState === 'interactive') {
    // Livewire may already have booted before this script parses on
    // fast reloads — hydrate immediately in that case.
    hydrateFilters();
} else {
    document.addEventListener('DOMContentLoaded', () => hydrateFilters());
}

document.addEventListener('livewire:navigated', () => hydrateFilters());

// Belt-and-braces: any `.filters` node that lands in the DOM without
// the hydrated class (morph, navigate, deferred render) gets marked.
if (typeof MutationObserver !== 'undefined') {
    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            for (const node of mutation.addedNodes) {
                if (! (node instanceof Element)) {
                    continue;
                }

                if (node.matches?.('.filters:not(.is-hydrated)')) {
                    node.classList.add('is-hydrated');
                }

                hydrateFilters(node);
            }
        }
    });

    observer.observe(document.documentElement, { childList: true, subtree: true });
}
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
