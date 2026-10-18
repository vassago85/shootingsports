{{-- Hide the "0" badge on table filter triggers. The vendor view
     unconditionally sets the badge to the active-filter count, so a
     bare zero shows on every table without filters applied. A
     MutationObserver keeps the DOM in sync as Livewire re-renders. --}}
<script>
    (function () {
        // The filter trigger wrapper is stable across Filament 5
        // variants; the badge inside it may or may not be wrapped in
        // a fi-icon-btn-badge-ctn. Match both shapes.
        const TRIGGER = '.fi-ta-filters-trigger-action-ctn';

        const scrub = () => {
            document.querySelectorAll(TRIGGER).forEach((trigger) => {
                trigger.querySelectorAll('.fi-badge, .fi-icon-btn-badge-ctn').forEach((node) => {
                    const text = (node.textContent || '').trim();
                    const isZero = text === '' || text === '0';
                    node.style.display = isZero ? 'none' : '';
                });
            });
        };

        const start = () => {
            scrub();

            const observer = new MutationObserver(() => {
                // Cheap: just scrub the whole document again. The DOM
                // under a trigger is tiny, and the observer fires on
                // batched morphs so this is called at most a few times
                // per second.
                scrub();
            });

            observer.observe(document.body, {
                childList: true,
                characterData: true,
                subtree: true,
            });
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', start);
        } else {
            start();
        }
    })();
</script>
