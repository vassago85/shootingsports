/*
 * Targets magazine flipbook.
 *
 * Renders a PDF to canvas thumbnails with pdf.js and then hands those
 * thumbnails to StPageFlip (page-flip) to drive the page-turn
 * animation. Keeps the DOM dependency to a single container element
 * (resources/views/public/magazines/show.blade.php) so the magazine
 * show page stays serverside-authored for SEO.
 *
 * Read tracking: posts to /magazines/{slug}/read on first open and
 * each page turn via navigator.sendBeacon. The server deduplicates
 * on an opaque reader hash, so page turns raise max_page and
 * pages_viewed without inflating the unique-reader count.
 */
import * as pdfjsLib from 'pdfjs-dist/build/pdf.mjs';
import pdfWorkerUrl from 'pdfjs-dist/build/pdf.worker.mjs?url';
import { PageFlip } from 'page-flip';

pdfjsLib.GlobalWorkerOptions.workerSrc = pdfWorkerUrl;

const RENDER_SCALE = 1.5;
const DESKTOP_BREAKPOINT = 900;

const beaconedPages = new Set();

const container = document.getElementById('magazine-flipbook');

if (container && container.dataset.pdfUrl) {
    bootFlipbook(container).catch((error) => {
        console.error('Flipbook failed to load', error);
        showFallback(container);
    });
}

async function bootFlipbook(root) {
    const pdfUrl = root.dataset.pdfUrl;
    const readUrl = root.dataset.readUrl;
    const trackingEnabled = root.dataset.track === '1';

    const task = pdfjsLib.getDocument({ url: pdfUrl });
    const pdf = await task.promise;

    const pages = [];
    const dimensions = { width: 800, height: 1100 };

    for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber += 1) {
        // eslint-disable-next-line no-await-in-loop
        const page = await pdf.getPage(pageNumber);
        const viewport = page.getViewport({ scale: RENDER_SCALE });
        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d');
        canvas.width = viewport.width;
        canvas.height = viewport.height;

        // eslint-disable-next-line no-await-in-loop
        await page.render({ canvasContext: context, viewport }).promise;

        if (pageNumber === 1) {
            dimensions.width = viewport.width;
            dimensions.height = viewport.height;
        }

        pages.push(canvas.toDataURL('image/jpeg', 0.82));
    }

    // Hand off to StPageFlip. The container's loading placeholder is
    // replaced; page-flip injects its own wrapper, canvas, and shadow
    // layers inside the root element.
    root.innerHTML = '';
    root.setAttribute('tabindex', '0');

    const isMobile = window.matchMedia(`(max-width: ${DESKTOP_BREAKPOINT - 1}px)`).matches;
    const flip = new PageFlip(root, {
        width: dimensions.width,
        height: dimensions.height,
        size: 'stretch',
        minWidth: 280,
        maxWidth: 1400,
        minHeight: 380,
        maxHeight: 1800,
        maxShadowOpacity: 0.5,
        showCover: true,
        mobileScrollSupport: true,
        usePortrait: true,
        drawShadow: true,
        flippingTime: 700,
        startPage: 0,
    });

    flip.loadFromImages(pages);

    if (isMobile && typeof flip.getOrientation === 'function') {
        // page-flip auto-switches to single-page mode when the
        // container is narrower than two pages — nothing extra to do.
    }

    const beacon = (page) => {
        if (!trackingEnabled || !readUrl) {
            return;
        }

        if (beaconedPages.has(page)) {
            return;
        }

        beaconedPages.add(page);

        const payload = new FormData();
        payload.append('page', String(page));
        payload.append('_token', csrfToken());

        if (typeof navigator.sendBeacon === 'function') {
            navigator.sendBeacon(readUrl, payload);
            return;
        }

        // Fallback: fire-and-forget fetch for browsers without sendBeacon.
        fetch(readUrl, {
            method: 'POST',
            body: payload,
            keepalive: true,
            credentials: 'same-origin',
        }).catch(() => {});
    };

    // Open event: count the reader on first paint.
    flip.on('init', () => beacon(1));
    flip.on('flip', (event) => {
        const page = Number(event?.data ?? 0) + 1;

        if (Number.isFinite(page) && page >= 1) {
            beacon(page);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (document.activeElement && document.activeElement.closest('input, textarea, select')) {
            return;
        }

        if (event.key === 'ArrowRight' || event.key === 'PageDown') {
            flip.flipNext();
        } else if (event.key === 'ArrowLeft' || event.key === 'PageUp') {
            flip.flipPrev();
        }
    });
}

function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');

    return meta ? meta.getAttribute('content') ?? '' : '';
}

function showFallback(root) {
    root.innerHTML = `
        <p class="empty">
            We couldn't load the flipbook in this browser. Try the PDF
            or the "Read as text" view below.
        </p>
    `;
}
