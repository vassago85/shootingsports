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
// Legacy build ships ES5-ish output plus polyfills (Map.getOrInsertComputed,
// Promise.withResolvers, etc.) so the flipbook works in older browsers and
// in Electron shells like the Cursor-embedded Chrome 144 we test against.
// Switching back to the modern build caps us at ~Chrome 149+ / Safari 26+.
import * as pdfjsLib from 'pdfjs-dist/legacy/build/pdf.mjs';
import pdfWorkerUrl from 'pdfjs-dist/legacy/build/pdf.worker.mjs?url';
import { PageFlip } from 'page-flip';

pdfjsLib.GlobalWorkerOptions.workerSrc = pdfWorkerUrl;

const MAX_RENDER_SCALE = 2.5;
const JPEG_QUALITY = 0.92;

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

    const firstPage = await pdf.getPage(1);
    const baseViewport = firstPage.getViewport({ scale: 1 });
    const pixelRatio = Math.min(Math.max(window.devicePixelRatio || 1, 1), 2);
    const screenPage = Math.min(window.screen?.width || 1440, 2560) / 2;
    const inlinePage = (root.clientWidth || 1000) / 2;
    const pageCssWidth = Math.min(Math.max(screenPage, inlinePage, 360), 1400);
    const renderScale = Math.min(
        MAX_RENDER_SCALE,
        Math.max(2, (pageCssWidth * pixelRatio) / baseViewport.width),
    );

    const pages = [];
    const dimensions = { width: 800, height: 1100 };

    for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber += 1) {
        // eslint-disable-next-line no-await-in-loop
        const page = pageNumber === 1 ? firstPage : await pdf.getPage(pageNumber);
        const viewport = page.getViewport({ scale: renderScale });
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

        pages.push(buildPageElement(canvas.toDataURL('image/jpeg', JPEG_QUALITY), viewport, await page.getAnnotations()));
    }

    // Hand off to StPageFlip. The container's loading placeholder is
    // replaced; page-flip injects its own wrapper, canvas, and shadow
    // layers inside the root element.
    root.innerHTML = '';
    root.setAttribute('tabindex', '0');

    const flip = new PageFlip(root, {
        width: dimensions.width,
        height: dimensions.height,
        size: 'stretch',
        minWidth: 280,
        maxWidth: 1400,
        minHeight: 380,
        maxHeight: 1600,
        maxShadowOpacity: 0.5,
        showCover: true,
        clickEventForward: true,
        mobileScrollSupport: true,
        usePortrait: true,
        drawShadow: true,
        flippingTime: 700,
        startPage: 0,
    });

    flip.loadFromHTML(pages);

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

    mountFullscreen(root.closest('.magazine-stage'), flip);

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

function buildPageElement(imageUrl, viewport, annotations) {
    const page = document.createElement('div');
    page.className = 'magazine-page';

    const image = document.createElement('img');
    image.src = imageUrl;
    image.alt = '';
    image.draggable = false;
    page.appendChild(image);

    for (const annotation of annotations) {
        const link = linkForAnnotation(annotation, viewport);

        if (link !== null) {
            page.appendChild(link);
        }
    }

    return page;
}

function linkForAnnotation(annotation, viewport) {
    if (annotation?.subtype !== 'Link' || !Array.isArray(annotation.rect)) {
        return null;
    }

    const href = safeHttpUrl(annotation.url || annotation.unsafeUrl);

    if (href === null) {
        return null;
    }

    const [x1, y1, x2, y2] = pdfRectToViewport(annotation.rect, viewport);
    const boxWidth = Math.abs(x2 - x1);
    const boxHeight = Math.abs(y2 - y1);

    if (boxWidth < 1 || boxHeight < 1) {
        return null;
    }

    const anchor = document.createElement('a');
    anchor.href = href;
    anchor.target = '_blank';
    anchor.rel = 'noopener noreferrer';
    anchor.title = href;
    anchor.style.left = `${(Math.min(x1, x2) / viewport.width) * 100}%`;
    anchor.style.top = `${(Math.min(y1, y2) / viewport.height) * 100}%`;
    anchor.style.width = `${(boxWidth / viewport.width) * 100}%`;
    anchor.style.height = `${(boxHeight / viewport.height) * 100}%`;

    return anchor;
}

function pdfRectToViewport(rect, viewport) {
    const transform = viewport?.transform;

    if (!Array.isArray(transform) || transform.length < 6) {
        return [0, 0, 0, 0];
    }

    const [a, b, c, d, e, f] = transform;
    const project = (x, y) => [a * x + c * y + e, b * x + d * y + f];
    const [x1, y1] = project(rect[0], rect[1]);
    const [x2, y2] = project(rect[2], rect[3]);

    return [x1, y1, x2, y2];
}

function safeHttpUrl(value) {
    if (typeof value !== 'string' || value.trim() === '') {
        return null;
    }

    try {
        const url = new URL(value, window.location.href);

        if (url.protocol === 'https:' || url.protocol === 'http:' || url.protocol === 'mailto:') {
            return url.href;
        }
    } catch {
        return null;
    }

    return null;
}

function mountFullscreen(stage, flip) {
    const button = document.getElementById('magazine-fullscreen');

    if (!stage || !button) {
        return;
    }

    const isReading = () => stage.classList.contains('is-reading') || document.fullscreenElement === stage;

    const refit = () => {
        const on = isReading();
        button.textContent = on ? 'Exit full screen' : 'Full screen';
        button.setAttribute('aria-pressed', on ? 'true' : 'false');
        document.documentElement.classList.toggle('magazine-reading', on);
        requestAnimationFrame(() => {
            requestAnimationFrame(() => flip.update());
        });
    };

    button.addEventListener('click', async () => {
        if (isReading()) {
            stage.classList.remove('is-reading');
            if (document.fullscreenElement) {
                await document.exitFullscreen().catch(() => {});
            }
            refit();
            return;
        }

        stage.classList.add('is-reading');
        const request = stage.requestFullscreen?.bind(stage) || stage.webkitRequestFullscreen?.bind(stage);
        if (request) {
            try {
                await request();
            } catch {
                // The fixed overlay still covers the page when the
                // browser refuses the Fullscreen API (common on iOS).
            }
        }
        refit();
    });

    let usingFullscreenApi = false;

    document.addEventListener('fullscreenchange', () => {
        if (document.fullscreenElement === stage) {
            usingFullscreenApi = true;
            stage.classList.add('is-reading');
        } else if (usingFullscreenApi) {
            usingFullscreenApi = false;
            stage.classList.remove('is-reading');
        }
        refit();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && stage.classList.contains('is-reading') && !document.fullscreenElement) {
            stage.classList.remove('is-reading');
            refit();
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
