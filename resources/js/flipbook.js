/*
 * Magazine reader.
 *
 * pdf.js draws each page at the size it is shown. Portrait pages stay
 * whole. A landscape sheet is a spread and is split at the gutter.
 * Page-flip copies the page element while it curls, and a copied canvas
 * is blank, so each animation frame copies the live pixels across.
 *
 * Links are stored on the server and painted onto the live leaves after
 * StPageFlip builds the book. The hidden source sheets never receive them.
 */
import { PageFlip } from 'page-flip';
import * as pdfjsLib from 'pdfjs-dist/legacy/build/pdf.mjs';
import pdfWorkerUrl from 'pdfjs-dist/legacy/build/pdf.worker.mjs?url';

pdfjsLib.GlobalWorkerOptions.workerSrc = pdfWorkerUrl;

const ZOOM_LEVELS = [1, 1.25, 1.5, 2];
const PORTRAIT_QUERY = '(max-width: 800px), (orientation: portrait) and (max-width: 1024px)';
const beaconedPages = new Set();

const container = document.getElementById('magazine-flipbook');

if (container && container.dataset.pdfUrl) {
    bootReader(container).catch((error) => {
        console.error('Magazine failed to load', error);
        showFallback(container);
    });
}

async function bootReader(root) {
    const pdfUrl = root.dataset.pdfUrl;
    const readUrl = root.dataset.readUrl;
    const progressUrl = root.dataset.progressUrl || '';
    const slug = root.dataset.slug || '';
    const trackingEnabled = root.dataset.track === '1';
    const hotspots = readJson('magazine-hotspots');
    const pageImages = readJson('magazine-page-images');
    const storageKey = slug ? `magazine-page:${slug}` : '';

    const pdf = await pdfjsLib.getDocument({
        url: pdfUrl,
        disableAutoFetch: true,
        disableStream: false,
        rangeChunkSize: 65536,
        isEvalSupported: false,
    }).promise;

    const first = await pdf.getPage(1);
    const base = first.getViewport({ scale: 1 });
    const sheets = [];

    for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber += 1) {
        const sheet = document.createElement('div');
        sheet.className = 'magazine-sheet';
        sheet.dataset.page = String(pageNumber);

        const canvas = document.createElement('canvas');
        canvas._live = true;
        sheet.append(canvas);

        sheets.push({
            pageNumber,
            sheet,
            canvas,
            task: null,
            key: '',
            logical: pageNumber,
            imageUrl: pageImages[pageNumber - 1] || '',
            pdfWidth: base.width,
            pdfHeight: base.height,
            spread: base.width > base.height * 1.15,
            sized: pageNumber === 1,
        });
    }

    const makeLeaf = (entry, half, logical) => {
        const element = document.createElement('div');
        element.className = 'magazine-leaf';
        element.dataset.page = String(entry.pageNumber);
        element.dataset.half = half;
        element.dataset.logical = String(logical);

        const canvas = document.createElement('canvas');
        const links = document.createElement('div');
        links.className = 'magazine-links';
        element.append(canvas, links);

        return { sheet: entry, half, logical, element, canvas, links };
    };

    const sources = document.createElement('div');
    sources.className = 'magazine-sources';
    sources.hidden = true;

    const leaves = [];
    let logicalCursor = 1;

    for (const entry of sheets) {
        sources.append(entry.sheet);

        if (entry.spread) {
            entry.logical = logicalCursor;
            leaves.push(makeLeaf(entry, 'left', logicalCursor), makeLeaf(entry, 'right', logicalCursor + 1));
            logicalCursor += 2;
            continue;
        }

        entry.logical = logicalCursor;
        const leaf = makeLeaf(entry, 'full', logicalCursor);
        logicalCursor += 1;

        if (entry.pageNumber === 1 || entry.pageNumber === pdf.numPages) {
            leaf.element.dataset.density = 'hard';
        }

        leaves.push(leaf);
    }

    const totalPages = leaves.length === 0 ? 1 : leaves[leaves.length - 1].logical;

    const leafIndexForLogical = (page) => {
        const index = leaves.findIndex((leaf) => leaf.logical === page);

        return index < 0 ? 0 : index;
    };

    const requested = Number(root.dataset.startPage)
        || Number(root.dataset.resumePage)
        || Number(storageKey ? localStorage.getItem(storageKey) : 0)
        || 1;

    let pageIndex = leafIndexForLogical(Math.min(Math.max(requested, 1), totalPages));
    let zoomIndex = 0;
    let flip = null;
    let currentSpec = null;
    let lastLayoutKey = '';
    let paintToken = 0;
    let painting = false;
    const paintQueue = [];
    let idleTimer = 0;

    root.setAttribute('tabindex', '0');

    const previous = button('Previous page', 'Previous');
    const next = button('Next page', 'Next');
    const zoomOut = button('Zoom out', '−');
    const zoomIn = button('Zoom in', '+');
    const thumbs = button('Page thumbnails', 'Pages');
    thumbs.setAttribute('aria-expanded', 'false');
    thumbs.setAttribute('aria-controls', 'magazine-thumbs');

    const jump = document.createElement('input');
    jump.type = 'number';
    jump.min = '1';
    jump.max = String(totalPages);
    jump.className = 'magazine-jump';
    jump.setAttribute('aria-label', 'Go to page');
    jump.inputMode = 'numeric';

    const status = document.createElement('span');
    status.className = 'magazine-status';
    status.setAttribute('aria-live', 'polite');

    const nav = root.closest('.magazine-stage')?.querySelector('[data-magazine-nav]');

    if (nav) {
        nav.append(previous, status, jump, next, thumbs, zoomOut, zoomIn);
    }

    const stage = root.closest('.magazine-stage');
    const fullscreenButton = document.getElementById('magazine-fullscreen');

    const beacon = (pageNumber) => {
        if (!trackingEnabled || !readUrl || beaconedPages.has(pageNumber)) {
            return;
        }

        beaconedPages.add(pageNumber);

        const payload = new FormData();
        payload.append('page', String(pageNumber));
        payload.append('_token', csrfToken());

        if (typeof navigator.sendBeacon === 'function') {
            navigator.sendBeacon(readUrl, payload);

            return;
        }

        fetch(readUrl, {
            method: 'POST',
            body: payload,
            keepalive: true,
            credentials: 'same-origin',
        }).catch(() => {});
    };

    const remember = (logical) => {
        const url = new URL(window.location.href);

        if (url.searchParams.get('page') !== String(logical)) {
            url.searchParams.set('page', String(logical));
            history.replaceState({}, '', url);
        }

        if (storageKey) {
            localStorage.setItem(storageKey, String(logical));
        }

        if (!progressUrl) {
            return;
        }

        const payload = new FormData();
        payload.append('page', String(logical));
        payload.append('_token', csrfToken());

        if (typeof navigator.sendBeacon === 'function') {
            navigator.sendBeacon(progressUrl, payload);

            return;
        }

        fetch(progressUrl, {
            method: 'POST',
            body: payload,
            keepalive: true,
            credentials: 'same-origin',
        }).catch(() => {});
    };

    const portraitNow = () => window.matchMedia(PORTRAIT_QUERY).matches;

    const measure = () => {
        const host = stage ?? root;
        const toolbar = document.getElementById('magazine-toolbar');
        const reading = host.classList.contains('is-reading') || document.fullscreenElement === host;
        const idle = host.classList.contains('is-idle');
        const toolbarHeight = reading && idle ? 0 : (toolbar?.offsetHeight ?? 0);

        if (stage) {
            stage.style.paddingBottom = `${toolbarHeight}px`;
        }

        const zoom = ZOOM_LEVELS[zoomIndex];
        const portrait = portraitNow();
        const sample = sheets.find((entry) => entry.spread) ?? sheets[0];
        const onePageAspect = sample
            ? (sample.spread ? (sample.pdfWidth / 2) / sample.pdfHeight : sample.pdfWidth / sample.pdfHeight)
            : 0.7;
        const bookAspect = portrait ? onePageAspect : onePageAspect * (sample?.spread ? 2 : 2);
        const widthBudget = Math.max((host.clientWidth || root.clientWidth) - 16, 280);
        const heightBudget = Math.max((host.clientHeight || window.innerHeight * 0.7) - toolbarHeight - 12, 240);
        let pageWidth = widthBudget;
        let pageHeight = pageWidth / bookAspect;

        if (pageHeight > heightBudget) {
            pageHeight = heightBudget;
            pageWidth = pageHeight * bookAspect;
        }

        pageWidth = Math.max(160, pageWidth * zoom);
        pageHeight = Math.max(160, pageHeight * zoom);

        return {
            portrait,
            zoom,
            pageWidth,
            pageHeight,
            pixelRatio: Math.min(Math.max(window.devicePixelRatio || 1, 1), 2),
        };
    };

    const currentLeaf = () => (flip ? flip.getCurrentPageIndex() : pageIndex);

    const frameFor = (entry, spec, boxWidth, boxHeight) => {
        const pageAspect = entry.pdfWidth / entry.pdfHeight;
        let cssWidth = boxWidth;
        let cssHeight = boxWidth / pageAspect;

        if (cssHeight > boxHeight) {
            cssHeight = boxHeight;
            cssWidth = boxHeight * pageAspect;
        }

        return {
            cssWidth,
            cssHeight,
            left: (boxWidth - cssWidth) / 2,
            top: (boxHeight - cssHeight) / 2,
        };
    };

    const placeCanvas = (node, frame, shift = 0) => {
        node.style.position = 'absolute';
        node.style.left = `${frame.left - shift}px`;
        node.style.top = `${frame.top}px`;
        node.style.width = `${frame.cssWidth}px`;
        node.style.height = `${frame.cssHeight}px`;
    };

    const placeLeaf = (leaf, spec) => {
        const leafWidth = spec.portrait ? spec.pageWidth : spec.pageWidth / 2;
        const frame = leaf.half === 'full'
            ? frameFor(leaf.sheet, spec, leafWidth, spec.pageHeight)
            : frameFor(leaf.sheet, spec, spec.pageWidth, spec.pageHeight);
        const shift = leaf.half === 'right' ? frame.cssWidth / 2 : 0;
        placeCanvas(leaf.canvas, frame, shift);
    };

    const visibleLeafIndexes = () => {
        const index = currentLeaf();
        const portrait = currentSpec?.portrait ?? portraitNow();

        if (portrait || index <= 0 || index >= leaves.length - 1) {
            return [index];
        }

        return [index, index + 1];
    };

    const paintLinks = () => {
        const visible = new Set(visibleLeafIndexes());

        for (const leaf of leaves) {
            const host = leaf.element.querySelector('.magazine-links') ?? leaf.links;
            host.replaceChildren();

            for (const hotspot of hotspotsForLeaf(leaf, hotspots)) {
                const anchor = document.createElement('a');
                anchor.href = hotspot.href;
                anchor.target = '_blank';
                anchor.rel = 'noopener sponsored';
                anchor.title = hotspot.label || 'Advertiser link';
                anchor.setAttribute('aria-label', hotspot.label || 'Advertiser link');
                anchor.style.left = `${hotspot.x}%`;
                anchor.style.top = `${hotspot.y}%`;
                anchor.style.width = `${hotspot.w}%`;
                anchor.style.height = `${hotspot.h}%`;
                anchor.tabIndex = visible.has(leaves.indexOf(leaf)) ? 0 : -1;

                const stop = (event) => event.stopPropagation();

                for (const type of ['pointerdown', 'mousedown', 'touchstart', 'click']) {
                    anchor.addEventListener(type, stop, true);
                }

                anchor.addEventListener('click', () => {
                    if (typeof window.umami?.track === 'function') {
                        window.umami.track('magazine_link', {
                            page: String(hotspot.page),
                            label: hotspot.label || '',
                        });
                    }
                });

                host.append(anchor);
            }
        }
    };

    const updateChrome = () => {
        const index = currentLeaf();
        const leaf = leaves[index];
        const zoom = ZOOM_LEVELS[zoomIndex];
        const portrait = currentSpec?.portrait ?? portraitNow();
        const alone = portrait || index <= 0 || index >= leaves.length - 1;
        const start = leaf?.logical ?? 1;
        const end = alone ? start : (leaves[index + 1]?.logical ?? start);
        status.textContent = alone
            ? `Page ${start} of ${totalPages}`
            : `Pages ${start}–${end} of ${totalPages}`;
        jump.value = String(start);
        previous.disabled = index <= 0;
        next.disabled = index >= leaves.length - 1;
        zoomOut.disabled = zoomIndex <= 0;
        zoomIn.disabled = zoomIndex >= ZOOM_LEVELS.length - 1;
        previous.setAttribute('aria-disabled', previous.disabled ? 'true' : 'false');
        next.setAttribute('aria-disabled', next.disabled ? 'true' : 'false');

        const book = root.querySelector('.magazine-book');

        if (book) {
            book.classList.toggle('is-cover', !portrait && index === 0);
            book.classList.toggle('is-back', !portrait && index >= leaves.length - 1 && index !== 0);
        }

        for (const visible of visibleLeafIndexes()) {
            const logical = leaves[visible]?.logical;

            if (logical) {
                beacon(logical);
            }
        }

        if (leaf) {
            remember(alone ? start : start);
        }

        paintLinks();
    };

    const copyCanvasClones = () => {
        for (const canvas of root.querySelectorAll('canvas')) {
            if (canvas._live) {
                continue;
            }

            const pageNumber = Number(canvas.closest('[data-page]')?.dataset.page);
            const half = canvas.closest('[data-half]')?.dataset.half || 'full';
            const source = leaves.find((leaf) => leaf.sheet.pageNumber === pageNumber && leaf.half === half)?.canvas
                ?? sheets[pageNumber - 1]?.canvas;

            if (!source?.width) {
                continue;
            }

            if (canvas.width !== source.width || canvas.height !== source.height) {
                canvas.width = source.width;
                canvas.height = source.height;
            }

            const context = canvas.getContext('2d');

            if (context) {
                context.drawImage(source, 0, 0);
            }
        }
    };

    let watchUntil = 0;
    let watching = false;

    const watchClones = () => {
        watchUntil = performance.now() + 900;

        if (watching) {
            return;
        }

        watching = true;

        const tick = () => {
            copyCanvasClones();

            if (performance.now() < watchUntil) {
                requestAnimationFrame(tick);

                return;
            }

            watching = false;
        };

        requestAnimationFrame(tick);
    };

    const ensureSize = async (entry) => {
        if (entry.sized) {
            return;
        }

        const page = await pdf.getPage(entry.pageNumber);
        const view = page.getViewport({ scale: 1 });
        entry.pdfWidth = view.width;
        entry.pdfHeight = view.height;
        entry.spread = view.width > view.height * 1.15;
        entry.sized = true;
    };

    const paint = async (entry, spec, token) => {
        await ensureSize(entry);

        if (token !== paintToken) {
            return;
        }

        const key = `${Math.round(spec.pageWidth)}x${Math.round(spec.pageHeight)}@${spec.pixelRatio.toFixed(2)}@${spec.zoom}`;

        if (entry.key === key) {
            return;
        }

        try {
            entry.task?.cancel();
        } catch {
            // The previous draw had already finished.
        }

        const leafWidth = spec.portrait ? spec.pageWidth : spec.pageWidth / 2;
        const frame = frameFor(entry, spec, entry.spread ? spec.pageWidth : leafWidth, spec.pageHeight);
        const ratio = spec.pixelRatio;
        const context = entry.canvas.getContext('2d', { alpha: false });

        if (entry.imageUrl && spec.zoom <= 1) {
            const image = await loadImage(entry.imageUrl);

            if (token !== paintToken) {
                return;
            }

            entry.canvas.width = Math.max(1, Math.floor(frame.cssWidth * ratio));
            entry.canvas.height = Math.max(1, Math.floor(frame.cssHeight * ratio));
            context?.drawImage(image, 0, 0, entry.canvas.width, entry.canvas.height);
        } else {
            const page = await pdf.getPage(entry.pageNumber);

            if (token !== paintToken) {
                return;
            }

            const viewport = page.getViewport({
                scale: (frame.cssWidth / entry.pdfWidth) * ratio,
            });
            entry.canvas.width = Math.max(1, Math.floor(viewport.width));
            entry.canvas.height = Math.max(1, Math.floor(viewport.height));
            const task = page.render({ canvas: entry.canvas, canvasContext: context, viewport });
            entry.task = task;

            try {
                await task.promise;
            } catch (error) {
                if (error?.name === 'RenderingCancelledException') {
                    return;
                }

                throw error;
            }
        }

        if (token !== paintToken) {
            return;
        }

        entry.key = key;
        entry.canvas._live = true;
        placeCanvas(entry.canvas, frame);

        for (const leaf of leaves) {
            if (leaf.sheet === entry && currentSpec) {
                placeLeaf(leaf, currentSpec);
                await copyLeaf(leaf, entry, spec);
            }
        }

        copyCanvasClones();
        paintLinks();
    };

    const copyLeaf = async (leaf, entry, spec) => {
        if (!entry.canvas.width) {
            return;
        }

        if (leaf.half === 'full') {
            leaf.canvas.width = entry.canvas.width;
            leaf.canvas.height = entry.canvas.height;
            leaf.canvas.getContext('2d')?.drawImage(entry.canvas, 0, 0);

            return;
        }

        const width = Math.max(1, Math.floor(entry.canvas.width / 2));
        const height = entry.canvas.height;
        leaf.canvas.width = width;
        leaf.canvas.height = height;
        const context = leaf.canvas.getContext('2d');
        const sx = leaf.half === 'right' ? width : 0;
        context?.drawImage(entry.canvas, sx, 0, width, height, 0, 0, width, height);
    };

    const drain = async () => {
        if (painting) {
            return;
        }

        painting = true;

        try {
            while (paintQueue.length > 0) {
                const entry = paintQueue.shift();
                const token = paintToken;
                const spec = currentSpec;

                if (!entry || !spec) {
                    continue;
                }

                try {
                    await paint(entry, spec, token);
                } catch (error) {
                    console.error('Magazine page failed to draw', error);
                }
            }
        } finally {
            painting = false;
        }

        if (paintQueue.length > 0) {
            drain();
        }
    };

    const requestPaint = (entries) => {
        for (const entry of entries) {
            if (entry && !paintQueue.includes(entry)) {
                paintQueue.push(entry);
            }
        }

        drain();
    };

    const nearbySheets = () => {
        const index = sheets.indexOf(leaves[currentLeaf()]?.sheet);
        const keep = [];

        for (let cursor = index - 4; cursor <= index + 5; cursor += 1) {
            if (sheets[cursor]) {
                keep.push(sheets[cursor]);
            }
        }

        return keep;
    };

    const releaseFarSheets = (keep) => {
        const held = new Set(keep);

        for (const entry of sheets) {
            if (held.has(entry) || !entry.key) {
                continue;
            }

            try {
                entry.task?.cancel();
            } catch {
                // Already finished.
            }

            entry.canvas.width = 0;
            entry.canvas.height = 0;
            entry.key = '';
        }
    };

    const refreshVisible = () => {
        const keep = nearbySheets();
        releaseFarSheets(keep);
        requestPaint(keep);
    };

    const releaseSheet = (sheet) => {
        sheet.classList.remove('stf__item', '--hard', '--soft', '--simple', '--left', '--right');
        sheet.style.cssText = '';
    };

    const mountFlip = (spec) => {
        if (flip) {
            pageIndex = flip.getCurrentPageIndex();
            flip.destroy();
            flip = null;
        }

        for (const entry of sheets) {
            releaseSheet(entry.sheet);
        }

        for (const leaf of leaves) {
            releaseSheet(leaf.element);
        }

        const book = document.createElement('div');
        book.className = 'magazine-book';
        root.replaceChildren(sources, book);

        for (const leaf of leaves) {
            book.append(leaf.element);
        }

        const leafWidth = Math.max(1, Math.round(spec.portrait ? spec.pageWidth : spec.pageWidth / 2));
        const height = Math.round(spec.pageHeight);

        flip = new PageFlip(book, {
            width: leafWidth,
            height,
            size: 'fixed',
            showCover: true,
            usePortrait: spec.portrait,
            autoSize: false,
            drawShadow: true,
            maxShadowOpacity: 0.35,
            flippingTime: 650,
            mobileScrollSupport: false,
            clickEventForward: false,
            useMouseEvents: true,
            disableFlipByClick: true,
            showPageCorners: true,
            startPage: Math.max(0, Math.min(pageIndex, leaves.length - 1)),
            swipeDistance: 30,
        });

        flip.loadFromHTML(leaves.map((leaf) => leaf.element));

        flip.on('flip', (event) => {
            pageIndex = event.data;
            updateChrome();
            refreshVisible();
            watchClones();
        });

        flip.on('changeState', (event) => {
            if (event.data === 'flipping' || event.data === 'user_fold' || event.data === 'fold_corner') {
                watchClones();
            }
        });

        for (const leaf of leaves) {
            placeLeaf(leaf, spec);
        }

        copyCanvasClones();
        updateChrome();
        root.classList.add('is-ready');
    };

    const resizeFlip = (spec) => {
        if (!flip) {
            mountFlip(spec);

            return;
        }

        const settings = flip.getSettings();
        const leafWidth = Math.max(1, Math.round(spec.portrait ? spec.pageWidth : spec.pageWidth / 2));
        const height = Math.round(spec.pageHeight);
        settings.width = leafWidth;
        settings.height = height;
        settings.usePortrait = spec.portrait;

        const book = root.querySelector('.magazine-book');
        const spreadWidth = spec.portrait ? leafWidth : leafWidth * 2;

        if (book) {
            book.style.width = `${spreadWidth}px`;
            book.style.height = `${height}px`;
        }

        for (const leaf of leaves) {
            placeLeaf(leaf, spec);
        }

        flip.update();
        copyCanvasClones();
        updateChrome();
    };

    const layout = async () => {
        const spec = measure();
        const key = `${spec.portrait}|${Math.round(spec.pageWidth)}|${Math.round(spec.pageHeight)}|${spec.pixelRatio.toFixed(2)}|${spec.zoom}`;

        if (key === lastLayoutKey && flip) {
            return;
        }

        const modeChanged = currentSpec ? currentSpec.portrait !== spec.portrait : true;
        lastLayoutKey = key;
        currentSpec = spec;
        paintQueue.length = 0;
        paintToken += 1;
        const token = paintToken;
        root.classList.toggle('is-zoomed', spec.zoom > 1);

        const keep = nearbySheets();

        for (const entry of keep.slice(0, 4)) {
            await paint(entry, spec, token);

            if (token !== paintToken) {
                return;
            }
        }

        if (!flip || modeChanged) {
            mountFlip(spec);
        } else {
            resizeFlip(spec);
        }

        refreshVisible();
    };

    const go = (direction) => {
        if (!flip) {
            return;
        }

        if (direction < 0 && previous.disabled) {
            return;
        }

        if (direction > 0 && next.disabled) {
            return;
        }

        if (direction > 0) {
            flip.flipNext();
        } else {
            flip.flipPrev();
        }
    };

    const turnTo = (logical) => {
        if (!flip) {
            return;
        }

        const index = leafIndexForLogical(Math.min(Math.max(logical, 1), totalPages));
        flip.turnToPage(index);
        pageIndex = index;
        updateChrome();
        refreshVisible();
    };

    const setZoom = (index) => {
        const nextIndex = Math.max(0, Math.min(ZOOM_LEVELS.length - 1, index));

        if (nextIndex === zoomIndex) {
            return;
        }

        zoomIndex = nextIndex;

        for (const entry of sheets) {
            entry.key = '';
        }

        layout();
    };

    previous.addEventListener('click', () => go(-1));
    next.addEventListener('click', () => go(1));
    zoomOut.addEventListener('click', () => setZoom(zoomIndex - 1));
    zoomIn.addEventListener('click', () => setZoom(zoomIndex + 1));
    jump.addEventListener('change', () => turnTo(Number(jump.value)));
    thumbs.addEventListener('click', () => toggleThumbs(thumbs, sheets, pageImages, pdf, turnTo));

    document.getElementById('magazine-download-open')?.addEventListener('click', () => {
        document.getElementById('magazine-download')?.showModal();
    });
    document.getElementById('magazine-download-close')?.addEventListener('click', () => {
        document.getElementById('magazine-download')?.close();
    });

    stage?.addEventListener('click', (event) => {
        if (!(event.target instanceof Element)) {
            return;
        }

        if (event.target.closest('a, button, input, .magazine-toolbar, .magazine-thumbs, .magazine-links')) {
            return;
        }

        const bounds = (stage).getBoundingClientRect();
        go(event.clientX - bounds.left < bounds.width / 2 ? -1 : 1);
    });

    document.addEventListener('click', (event) => {
        const trigger = event.target instanceof Element
            ? event.target.closest('[data-magazine-jump]')
            : null;

        if (!trigger) {
            return;
        }

        event.preventDefault();
        turnTo(Number(trigger.getAttribute('data-magazine-jump')));
        stage?.scrollIntoView({ block: 'nearest' });
    });

    document.addEventListener('keydown', (event) => {
        if (!root.isConnected) {
            return;
        }

        if (event.key === 'Escape' && stage?.classList.contains('is-reading') && !document.fullscreenElement) {
            stage.classList.remove('is-reading');
            layout();

            return;
        }

        if (event.target instanceof Element && event.target.closest('input, textarea, select')) {
            return;
        }

        if (event.key === 'ArrowRight' || event.key === 'PageDown') {
            event.preventDefault();
            go(1);
        } else if (event.key === 'ArrowLeft' || event.key === 'PageUp') {
            event.preventDefault();
            go(-1);
        } else if (event.key === 'Home') {
            event.preventDefault();
            turnTo(1);
        } else if (event.key === 'End') {
            event.preventDefault();
            turnTo(totalPages);
        } else if (event.key === '+' || event.key === '=') {
            setZoom(zoomIndex + 1);
        } else if (event.key === '-' || event.key === '_') {
            setZoom(zoomIndex - 1);
        } else if (event.key === 'f' || event.key === 'F') {
            event.preventDefault();
            fullscreenButton?.click();
        }
    });

    const poke = () => {
        const wasIdle = stage?.classList.contains('is-idle') ?? false;
        stage?.classList.remove('is-idle');

        if (wasIdle) {
            layout();
        }

        window.clearTimeout(idleTimer);
        idleTimer = window.setTimeout(() => {
            if (stage?.classList.contains('is-reading') || document.fullscreenElement === stage) {
                stage?.classList.add('is-idle');
                layout();
            }
        }, 3000);
    };

    stage?.addEventListener('pointermove', poke);
    stage?.addEventListener('pointerdown', poke);
    stage?.addEventListener('focusin', poke);

    bindPinch(stage ?? root, () => setZoom(zoomIndex + 1), () => setZoom(zoomIndex - 1));

    let resizeTimer = 0;
    const refitSoon = () => {
        window.clearTimeout(resizeTimer);
        resizeTimer = window.setTimeout(() => layout(), 150);
    };

    window.addEventListener('resize', refitSoon);
    window.matchMedia(PORTRAIT_QUERY).addEventListener('change', refitSoon);

    mountFullscreen(stage, () => layout());
    await layout();
    poke();
}

function hotspotsForLeaf(leaf, hotspots) {
    const page = leaf.sheet.pageNumber;

    return hotspots.flatMap((hotspot) => {
        if (Number(hotspot.pdfPage) !== page) {
            return [];
        }

        const x = Number(hotspot.x);
        const y = Number(hotspot.y);
        const w = Number(hotspot.w);
        const h = Number(hotspot.h);
        const right = x + w;

        if (leaf.half === 'full') {
            return [{ ...hotspot, x, y, w, h }];
        }

        if (leaf.half === 'left') {
            if (x >= 50) {
                return [];
            }

            const edge = Math.min(right, 50);

            return [{ ...hotspot, x: x * 2, y, w: (edge - x) * 2, h }];
        }

        if (right <= 50) {
            return [];
        }

        const edge = Math.max(x, 50);

        return [{ ...hotspot, x: (edge - 50) * 2, y, w: (Math.min(right, 100) - edge) * 2, h }];
    });
}

function toggleThumbs(trigger, sheets, pageImages, pdf, turnTo) {
    const drawer = document.getElementById('magazine-thumbs');

    if (!drawer) {
        return;
    }

    const open = drawer.hidden;
    drawer.hidden = !open;
    trigger.setAttribute('aria-expanded', open ? 'true' : 'false');

    if (!open || drawer.childElementCount > 0) {
        return;
    }

    const grid = document.createElement('div');
    grid.className = 'magazine-thumbs__grid';
    drawer.append(grid);

    sheets.forEach((entry, index) => {
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'magazine-thumbs__item';
        item.setAttribute('aria-label', `Go to page ${index + 1}`);
        item.addEventListener('click', () => {
            turnTo(entry.logical || index + 1);
            drawer.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
        });

        if (pageImages[index]) {
            const image = document.createElement('img');
            image.alt = '';
            image.loading = 'lazy';
            image.src = pageImages[index];
            item.append(image);
        } else {
            const canvas = document.createElement('canvas');
            item.append(canvas);
            observeThumb(item, canvas, () => pdf.getPage(entry.pageNumber));
        }

        const caption = document.createElement('span');
        caption.textContent = String(index + 1);
        item.append(caption);
        grid.append(item);
    });
}

function observeThumb(item, canvas, loadPage) {
    const paint = async () => {
        if (canvas.dataset.ready === '1') {
            return;
        }

        canvas.dataset.ready = '1';
        const page = await loadPage();
        const viewport = page.getViewport({ scale: 0.2 });
        canvas.width = Math.floor(viewport.width);
        canvas.height = Math.floor(viewport.height);
        await page.render({ canvas, canvasContext: canvas.getContext('2d'), viewport }).promise;
    };

    if (!('IntersectionObserver' in window)) {
        paint();

        return;
    }

    const observer = new IntersectionObserver((entries) => {
        if (entries.some((entry) => entry.isIntersecting)) {
            observer.disconnect();
            paint();
        }
    });
    observer.observe(item);
}

function bindPinch(target, zoomIn, zoomOut) {
    const points = new Map();
    let start = 0;

    target.addEventListener('pointerdown', (event) => {
        if (event.target instanceof Element && event.target.closest('a, button, input')) {
            return;
        }

        points.set(event.pointerId, { x: event.clientX, y: event.clientY });

        if (points.size === 2) {
            start = distance(points);
        }
    });

    target.addEventListener('pointermove', (event) => {
        if (!points.has(event.pointerId)) {
            return;
        }

        points.set(event.pointerId, { x: event.clientX, y: event.clientY });
    });

    const finish = (event) => {
        if (points.size === 2 && start > 0) {
            const next = distance(points);

            if (next > start * 1.12) {
                zoomIn();
            } else if (next < start * 0.88) {
                zoomOut();
            }
        }

        points.delete(event.pointerId);
        start = points.size === 2 ? distance(points) : 0;
    };

    target.addEventListener('pointerup', finish);
    target.addEventListener('pointercancel', finish);
}

function distance(points) {
    const [a, b] = [...points.values()];

    if (!a || !b) {
        return 0;
    }

    return Math.hypot(a.x - b.x, a.y - b.y);
}

function button(label, text) {
    const element = document.createElement('button');
    element.type = 'button';
    element.className = 'btn ghost';
    element.textContent = text;
    element.setAttribute('aria-label', label);

    return element;
}

function readJson(id) {
    const node = document.getElementById(id);

    if (!node?.textContent) {
        return [];
    }

    try {
        const parsed = JSON.parse(node.textContent);

        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
}

function loadImage(url) {
    return new Promise((resolve, reject) => {
        const image = new Image();
        image.decoding = 'async';
        image.onload = () => resolve(image);
        image.onerror = () => reject(new Error('Page image failed'));
        image.src = url;
    });
}

function mountFullscreen(stage, onRefit) {
    const control = document.getElementById('magazine-fullscreen');

    if (!stage || !control) {
        return;
    }

    const reading = () => stage.classList.contains('is-reading') || document.fullscreenElement === stage;

    const refit = () => {
        const on = reading();
        control.textContent = on ? 'Exit full screen' : 'Full screen';
        control.setAttribute('aria-label', on ? 'Exit full screen' : 'Full screen');
        control.setAttribute('aria-pressed', on ? 'true' : 'false');
        document.documentElement.classList.toggle('magazine-reading', on);
        requestAnimationFrame(() => {
            requestAnimationFrame(() => onRefit());
        });
    };

    control.addEventListener('click', async () => {
        if (reading()) {
            stage.classList.remove('is-reading', 'is-idle');

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
                // iOS Safari has no Fullscreen API here. The fixed
                // immersive layer still covers the viewport.
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
            stage.classList.remove('is-reading', 'is-idle');
        }

        refit();
    });
}

function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');

    return meta ? meta.getAttribute('content') ?? '' : '';
}

function showFallback(root) {
    root.innerHTML = `
        <p class="empty">
            We couldn't load the magazine in this browser. Try the PDF
            or the "Read as text" view below.
        </p>
    `;
}
