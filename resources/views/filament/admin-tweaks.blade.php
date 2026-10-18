{{-- Hide the "0" badge on table filter triggers. The vendor view
     unconditionally sets the badge to the active-filter count, so a
     bare zero shows on every table without filters applied. Live-mode
     checkbox-free: a plain MutationObserver keeps the DOM in sync. --}}
<script>
    (function () {
        const SELECTOR = '.fi-ta-filters-trigger-action-ctn .fi-icon-btn-badge-ctn';

        const scrub = (root) => {
            root.querySelectorAll(SELECTOR).forEach((node) => {
                const text = (node.textContent || '').trim();
                node.style.display = text === '0' ? 'none' : '';
            });
        };

        const run = () => {
            scrub(document);

            const observer = new MutationObserver((mutations) => {
                for (const mutation of mutations) {
                    if (mutation.type === 'childList' || mutation.type === 'characterData') {
                        scrub(document);
                        return;
                    }
                }
            });

            observer.observe(document.body, {
                childList: true,
                characterData: true,
                subtree: true,
            });
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', run);
        } else {
            run();
        }
    })();
</script>
