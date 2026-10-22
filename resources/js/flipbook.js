/*
 * Targets magazine reader.
 *
 * PDF.js draws each page to a canvas at the size it is shown, so type
 * stays as sharp as the file. Each PDF page is shown on its own, at
 * its own shape. The cover is portrait. Later pages are full spreads,
 * split at the gutter on a wide screen so page-flip curls one half
 * to the middle. The curl copies the page element, and a copied canvas
 * is blank, so each animation frame copies the live pixels across.
 *
 * On a narrow screen the same canvases slide in 2D, one page at a time.
 * Zoom throws the old bitmap away and draws the page again.
 *
 * Link annotations are placed on the same viewport. Read tracking posts
 * to /magazines/{slug}/read via navigator.sendBeacon.
 */
import { PageFlip } from 'page-flip';
import * as pdfjsLib from 'pdfjs-dist/legacy/build/pdf.mjs';
import pdfWorkerUrl from 'pdfjs-dist/legacy/build/pdf.worker.mjs?url';

pdfjsLib.GlobalWorkerOptions.workerSrc = pdfWorkerUrl;

const NARROW_QUERY = '(max-width: 700px)';
const ZOOM_LEVELS = [1, 1.25, 1.5, 2];
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
    const trackingEnabled = root.dataset.track === '1';

    const pdf = await pdfjsLib.getDocument({ url: pdfUrl }).promise;
    const first = await pdf.getPage(1);

    const sheets = [];

    for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber += 1) {
        const sheet = document.createElement('div');
        sheet.className = 'magazine-sheet';
        sheet.dataset.page = String(pageNumber);

        if (pageNumber === 1 || pageNumber === pdf.numPages) {
            sheet.dataset.density = 'hard';
        }

        const canvas = document.createElement('canvas');
        canvas._live = true;

        const links = document.createElement('div');
        links.className = 'magazine-links';
        sheet.append(canvas, links);

        const page = pageNumber === 1 ? first : await pdf.getPage(pageNumber);
        const view = page.getViewport({ scale: 1 });

        sheets.push({
            pageNumber,
            sheet,
            canvas,
            links,
            task: null,
            key: '',
            linked: false,
            pdfWidth: view.width,
            pdfHeight: view.height,
            spread: view.width > view.height * 1.15,
        });
    }

    const makeLeaf = (entry, half) => {
        const element = document.createElement('div');
        element.className = 'magazine-leaf';
        element.dataset.page = String(entry.pageNumber);
        element.dataset.half = half;

        const canvas = document.createElement('canvas');
        const links = document.createElement('div');
        links.className = 'magazine-links';
        element.append(canvas, links);

        return { sheet: entry, half, element, canvas, links };
    };

    const sources = document.createElement('div');
    sources.className = 'magazine-sources';
    sources.hidden = true;

    const leaves = [];

    for (const entry of sheets) {
        sources.append(entry.sheet);

        if (entry.spread) {
            leaves.push(makeLeaf(entry, 'left'), makeLeaf(entry, 'right'));
            continue;
        }

        const leaf = makeLeaf(entry, 'full');

        if (entry.pageNumber === 1 || entry.pageNumber === pdf.numPages) {
            leaf.element.dataset.density = 'hard';
        }

        leaves.push(leaf);
    }

    const leafIndexForSheet = (sheetIndex) => {
        const sheet = sheets[sheetIndex];
        const index = leaves.findIndex((leaf) => leaf.sheet === sheet);

        return index < 0 ? 0 : index;
    };

    const sheetIndexForLeaf = (leafIndex) => {
        const sheetIndex = sheets.indexOf(leaves[leafIndex]?.sheet);

        return sheetIndex < 0 ? 0 : sheetIndex;
    };

    const spreadPage = sheets.find((entry) => entry.spread) ?? sheets[0];
    const sheetAspect = spreadPage.pdfWidth / spreadPage.pdfHeight;

    let pageIndex = 0;
    let zoomIndex = 0;
    let flip = null;
    let mode = '';
    let currentSpec = null;
    let lastLayoutKey = '';
    let paintToken = 0;
    let painting = false;
    const paintQueue = [];

    root.setAttribute('tabindex', '0');

    const previous = document.createElement('button');
    previous.type = 'button';
    previous.className = 'btn ghost magazine-control magazine-control--prev';
    previous.textContent = 'Previous';

    const next = document.createElement('button');
    next.type = 'button';
    next.className = 'btn ghost magazine-control magazine-control--next';
    next.textContent = 'Next';

    const zoomOut = document.createElement('button');
    zoomOut.type = 'button';
    zoomOut.className = 'btn ghost magazine-control magazine-control--zoom';
    zoomOut.textContent = 'Zoom out';

    const zoomIn = document.createElement('button');
    zoomIn.type = 'button';
    zoomIn.className = 'btn ghost magazine-control magazine-control--zoom';
    zoomIn.textContent = 'Zoom in';

    const status = document.createElement('span');
    status.className = 'magazine-status';
    status.setAttribute('aria-live', 'polite');

    const bar = root.closest('.magazine-stage')?.querySelector('.magazine-stage__bar');
    const fullscreen = document.getElementById('magazine-fullscreen');

    if (bar && fullscreen) {
        const nav = document.createElement('div');
        nav.className = 'magazine-stage__nav';
        nav.append(previous, status, next);

        const tools = document.createElement('div');
        tools.className = 'magazine-stage__tools';
        tools.append(zoomOut, zoomIn);

        while (bar.firstChild) {
            tools.append(bar.firstChild);
        }

        bar.append(nav, tools);
    }

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

    const measure = () => {
        const stage = root.closest('.magazine-stage') ?? root;
        const toolbar = stage.querySelector('.magazine-stage__bar');
        const reading = stage.classList.contains('is-reading') || document.fullscreenElement === stage;
        const narrow = window.matchMedia(NARROW_QUERY).matches;
        const zoom = ZOOM_LEVELS[zoomIndex];
        const hostWidth = reading ? stage.clientWidth : root.clientWidth;
        const widthBudget = Math.max(hostWidth - (reading ? (narrow ? 16 : 40) : 0), narrow ? 160 : 320);
        const heightBudget = reading
            ? Math.max(stage.clientHeight - (toolbar?.offsetHeight ?? 64) - 28, 280)
            : Math.min(window.innerHeight * 0.78, 1100);
        let pageWidth = widthBudget;
        let pageHeight = pageWidth / sheetAspect;

        if (pageHeight > heightBudget) {
            pageHeight = heightBudget;
            pageWidth = pageHeight * sheetAspect;
        }

        pageWidth = Math.max(160, pageWidth * zoom);
        pageHeight = Math.max(160, pageHeight * zoom);
        const pixelRatio = Math.min(Math.max(window.devicePixelRatio || 1, 1), 2);

        return { narrow, zoom, pageWidth, pageHeight, pixelRatio };
    };

    const visibleIndexes = () => {
        const index = flip ? sheetIndexForLeaf(flip.getCurrentPageIndex()) : pageIndex;

        return [Math.max(0, Math.min(index, sheets.length - 1))];
    };

    const placeName = (index) => {
        const entry = sheets[index];

        if (!entry) {
            return '';
        }

        if (index === 0 && !entry.spread) {
            return 'Cover';
        }

        let slot = 1;

        for (let cursor = 0; cursor < index; cursor += 1) {
            slot += sheets[cursor].spread ? 2 : 1;
        }

        if (entry.spread) {
            return `Pages ${slot}–${slot + 1}`;
        }

        return `Page ${slot}`;
    };

    const frameFor = (entry, spec, boxWidth = spec.pageWidth, boxHeight = spec.pageHeight) => {
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

    const placeNodes = (nodes, frame, shift = 0) => {
        for (const node of nodes) {
            node.style.position = 'absolute';
            node.style.right = 'auto';
            node.style.bottom = 'auto';
            node.style.left = `${frame.left - shift}px`;
            node.style.top = `${frame.top}px`;
            node.style.width = `${frame.cssWidth}px`;
            node.style.height = `${frame.cssHeight}px`;
        }
    };

    const place = (entry, spec) => {
        placeNodes([entry.canvas, entry.links], frameFor(entry, spec));
    };

    const placeLeaf = (leaf, spec) => {
        const frame = leaf.half === 'full'
            ? frameFor(leaf.sheet, spec, spec.pageWidth / 2, spec.pageHeight)
            : frameFor(leaf.sheet, spec);
        const shift = leaf.half === 'right' ? frame.cssWidth / 2 : 0;

        placeNodes([leaf.canvas, leaf.links], frame, shift);
    };

    const syncLeafLinks = (entry) => {
        for (const leaf of leaves) {
            if (leaf.sheet !== entry) {
                continue;
            }

            leaf.links.replaceChildren();

            for (const anchor of entry.links.querySelectorAll('a')) {
                const copy = anchor.cloneNode(true);
                copy.addEventListener('click', (event) => event.stopPropagation());
                leaf.links.append(copy);
            }
        }
    };

    const updateChrome = () => {
        const indexes = visibleIndexes();
        const zoom = ZOOM_LEVELS[zoomIndex];

        status.textContent = `${placeName(indexes[0])} · ${Math.round(zoom * 100)}%`;
        previous.disabled = indexes[0] <= 0;
        next.disabled = indexes[0] >= sheets.length - 1;
        zoomOut.disabled = zoomIndex <= 0;
        zoomIn.disabled = zoomIndex >= ZOOM_LEVELS.length - 1;

        for (const index of indexes) {
            beacon(index + 1);
        }
    };

    const copyCanvasClones = () => {
        for (const canvas of root.querySelectorAll('canvas')) {
            if (canvas._live) {
                continue;
            }

            const pageNumber = Number(canvas.closest('[data-page]')?.dataset.page);
            const source = sheets[pageNumber - 1]?.canvas;

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

    const paint = async (entry, spec, token) => {
        const key = `${Math.round(spec.pageWidth)}x${Math.round(spec.pageHeight)}@${spec.pixelRatio.toFixed(2)}`;

        if (entry.key === key) {
            return;
        }

        try {
            entry.task?.cancel();
        } catch {
            // The previous draw had already finished.
        }

        const page = entry.pageNumber === 1 ? first : await pdf.getPage(entry.pageNumber);

        if (token !== paintToken) {
            return;
        }

        const frame = frameFor(entry, spec);
        const viewport = page.getViewport({
            scale: (frame.cssWidth / entry.pdfWidth) * spec.pixelRatio,
        });
        const context = entry.canvas.getContext('2d', { alpha: false });
        entry.canvas.width = Math.floor(viewport.width);
        entry.canvas.height = Math.floor(viewport.height);

        const task = page.render({ canvasContext: context, viewport });
        entry.task = task;

        try {
            await task.promise;
        } catch (error) {
            if (error?.name === 'RenderingCancelledException') {
                return;
            }

            throw error;
        }

        if (token !== paintToken) {
            return;
        }

        entry.key = key;
        entry.canvas._live = true;
        place(entry, spec);
        syncLeafLinks(entry);

        for (const leaf of leaves) {
            if (leaf.sheet === entry) {
                placeLeaf(leaf, spec);
            }
        }

        if (!entry.linked) {
            const annotations = await page.getAnnotations();
            entry.links.replaceChildren();

            for (const annotation of annotations) {
                const anchor = linkForAnnotation(annotation, viewport);

                if (anchor !== null) {
                    anchor.addEventListener('click', (event) => event.stopPropagation());
                    entry.links.appendChild(anchor);
                }
            }

            entry.linked = true;
        }

        copyCanvasClones();
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

                if (!spec) {
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

    const releaseSheet = (sheet) => {
        sheet.classList.remove('stf__item', '--hard', '--soft', '--simple', '--left', '--right');
        sheet.style.cssText = '';
    };

    const mountFlip = (spec) => {
        if (flip) {
            pageIndex = sheetIndexForLeaf(flip.getCurrentPageIndex());
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

        const leafWidth = Math.max(1, Math.round(spec.pageWidth / 2));
        const height = Math.round(spec.pageHeight);

        flip = new PageFlip(book, {
            width: leafWidth,
            height,
            size: 'fixed',
            showCover: sheets[0] ? !sheets[0].spread : true,
            usePortrait: false,
            autoSize: true,
            drawShadow: true,
            maxShadowOpacity: 0.35,
            flippingTime: 650,
            mobileScrollSupport: true,
            clickEventForward: true,
            useMouseEvents: true,
            disableFlipByClick: false,
            showPageCorners: true,
            startPage: leafIndexForSheet(pageIndex),
            swipeDistance: 30,
        });

        flip.loadFromHTML(leaves.map((leaf) => leaf.element));

        flip.on('flip', (event) => {
            pageIndex = sheetIndexForLeaf(event.data);
            updateChrome();
            const around = [pageIndex - 1, pageIndex, pageIndex + 1]
                .map((index) => sheets[index])
                .filter(Boolean);
            requestPaint(around);
        });

        flip.on('changeState', (event) => {
            if (event.data === 'flipping' || event.data === 'user_fold' || event.data === 'fold_corner') {
                watchClones();
            }
        });

        mode = 'flip';
        pageIndex = sheetIndexForLeaf(flip.getCurrentPageIndex());

        for (const entry of sheets) {
            place(entry, spec);
        }

        for (const leaf of leaves) {
            placeLeaf(leaf, spec);
        }

        copyCanvasClones();
        updateChrome();
    };

    const resizeFlip = (spec) => {
        const settings = flip.getSettings();
        const leafWidth = Math.max(1, Math.round(spec.pageWidth / 2));
        const height = Math.round(spec.pageHeight);
        const spreadWidth = leafWidth * 2;
        settings.width = leafWidth;
        settings.height = height;
        settings.minWidth = leafWidth;
        settings.maxWidth = leafWidth;
        settings.minHeight = height;
        settings.maxHeight = height;

        const book = root.querySelector('.magazine-book');

        if (book) {
            book.style.minWidth = `${spreadWidth}px`;
            book.style.minHeight = `${height}px`;
            book.style.width = spec.zoom > 1 ? `${spreadWidth}px` : '100%';
            book.style.maxWidth = spec.zoom > 1 ? 'none' : `${spreadWidth}px`;
        }

        for (const entry of sheets) {
            place(entry, spec);
        }

        for (const leaf of leaves) {
            placeLeaf(leaf, spec);
        }

        copyCanvasClones();

        flip.update();
        updateChrome();
    };

    const bindSwipe = (frame) => {
        let startX = 0;
        let startY = 0;
        let tracking = false;

        frame.addEventListener('pointerdown', (event) => {
            if (event.target.closest('a')) {
                return;
            }

            tracking = true;
            startX = event.clientX;
            startY = event.clientY;
        });

        frame.addEventListener('pointerup', (event) => {
            if (!tracking) {
                return;
            }

            tracking = false;
            const dx = event.clientX - startX;
            const dy = event.clientY - startY;

            if (Math.abs(dx) < 40 || Math.abs(dx) < Math.abs(dy)) {
                return;
            }

            go(dx < 0 ? 1 : -1);
        });
    };

    const showSlide = (index, direction) => {
        const frame = root.querySelector('.magazine-slide');
        const incoming = sheets[index]?.sheet;

        if (!frame || !incoming) {
            return;
        }

        releaseSheet(incoming);
        incoming.style.position = 'absolute';
        incoming.style.inset = '0';
        incoming.style.transition = 'none';
        incoming.style.transform = direction === 0 ? 'translateX(0)' : `translateX(${direction > 0 ? '100%' : '-100%'})`;
        frame.appendChild(incoming);

        if (currentSpec) {
            place(sheets[index], currentSpec);
        }

        const outgoing = [...frame.children].find((node) => node !== incoming);

        if (direction !== 0) {
            requestAnimationFrame(() => {
                incoming.style.transition = 'transform 420ms ease';
                incoming.style.transform = 'translateX(0)';

                if (outgoing) {
                    outgoing.style.transition = 'transform 420ms ease';
                    outgoing.style.transform = `translateX(${direction > 0 ? '-100%' : '100%'})`;
                    outgoing.addEventListener('transitionend', () => outgoing.remove(), { once: true });
                }
            });
        } else if (outgoing) {
            outgoing.remove();
        }

        pageIndex = index;
        updateChrome();
    };

    const mountSlide = (spec) => {
        if (flip) {
            pageIndex = sheetIndexForLeaf(flip.getCurrentPageIndex());
            flip.destroy();
            flip = null;
        }

        for (const entry of sheets) {
            releaseSheet(entry.sheet);
        }

        const frame = document.createElement('div');
        frame.className = 'magazine-slide';
        frame.style.width = `${Math.round(spec.pageWidth)}px`;
        frame.style.height = `${Math.round(spec.pageHeight)}px`;
        root.replaceChildren(frame);
        bindSwipe(frame);
        mode = 'slide';
        showSlide(Math.max(0, Math.min(pageIndex, sheets.length - 1)), 0);
    };

    const resizeSlide = (spec) => {
        const frame = root.querySelector('.magazine-slide');

        if (!frame) {
            mountSlide(spec);
            return;
        }

        frame.style.width = `${Math.round(spec.pageWidth)}px`;
        frame.style.height = `${Math.round(spec.pageHeight)}px`;
        updateChrome();
    };

    const applyMode = (spec) => {
        root.classList.toggle('is-2d', spec.narrow);
        root.classList.toggle('is-3d', !spec.narrow);
        root.classList.toggle('is-zoomed', spec.zoom > 1);

        if (spec.narrow) {
            if (mode !== 'slide') {
                mountSlide(spec);
            } else {
                resizeSlide(spec);
            }

            return;
        }

        if (mode !== 'flip') {
            mountFlip(spec);
            return;
        }

        resizeFlip(spec);
    };

    const layout = async () => {
        const spec = measure();
        const key = `${spec.narrow}|${Math.round(spec.pageWidth)}|${Math.round(spec.pageHeight)}|${spec.pixelRatio.toFixed(2)}`;

        if (key === lastLayoutKey) {
            return;
        }

        lastLayoutKey = key;
        currentSpec = spec;
        paintQueue.length = 0;
        paintToken += 1;
        const token = paintToken;
        const index = flip ? sheetIndexForLeaf(flip.getCurrentPageIndex()) : pageIndex;
        const indexes = [index, index + 1];

        const visible = indexes.map((page) => sheets[page]).filter(Boolean);

        for (const entry of visible) {
            await paint(entry, spec, token);

            if (token !== paintToken) {
                return;
            }
        }

        if (token !== paintToken) {
            return;
        }

        applyMode(spec);

        if (spec.zoom <= 1) {
            requestPaint(sheets.filter((entry) => !visible.includes(entry)));
        }
    };

    const go = (direction) => {
        if (direction < 0 && previous.disabled) {
            return;
        }

        if (direction > 0 && next.disabled) {
            return;
        }

        if (flip) {
            const index = sheetIndexForLeaf(flip.getCurrentPageIndex());
            const ahead = [index + direction];
            const pending = ahead
                .map((page) => sheets[page])
                .filter(Boolean)
                .map((entry) => paint(entry, currentSpec, paintToken));

            Promise.all(pending).then(() => {
                if (direction > 0) {
                    flip.flipNext('bottom');
                } else {
                    flip.flipPrev('bottom');
                }
            }).catch((error) => {
                console.error('Magazine page failed to draw', error);
            });

            return;
        }

        const index = Math.max(0, Math.min(sheets.length - 1, pageIndex + direction));
        const entry = sheets[index];

        if (currentSpec && entry) {
            paint(entry, currentSpec, paintToken).then(() => showSlide(index, direction));
            return;
        }

        showSlide(index, direction);
    };

    const setZoom = (index) => {
        const nextIndex = Math.max(0, Math.min(ZOOM_LEVELS.length - 1, index));

        if (nextIndex === zoomIndex) {
            return;
        }

        zoomIndex = nextIndex;
        layout();
    };

    previous.addEventListener('click', () => go(-1));
    next.addEventListener('click', () => go(1));
    zoomOut.addEventListener('click', () => setZoom(zoomIndex - 1));
    zoomIn.addEventListener('click', () => setZoom(zoomIndex + 1));

    document.addEventListener('keydown', (event) => {
        if (event.target instanceof Element && event.target.closest('input, textarea, select')) {
            return;
        }

        if (!root.isConnected) {
            return;
        }

        if (event.key === 'ArrowRight' || event.key === 'PageDown') {
            event.preventDefault();
            go(1);
        } else if (event.key === 'ArrowLeft' || event.key === 'PageUp') {
            event.preventDefault();
            go(-1);
        } else if (event.key === '+' || event.key === '=') {
            setZoom(zoomIndex + 1);
        } else if (event.key === '-' || event.key === '_') {
            setZoom(zoomIndex - 1);
        }
    });

    let resizeTimer = 0;
    window.addEventListener('resize', () => {
        window.clearTimeout(resizeTimer);
        resizeTimer = window.setTimeout(() => layout(), 150);
    });

    mountFullscreen(root.closest('.magazine-stage'), () => layout());

    await layout();
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

    if (boxWidth < 1 || boxHeight < 1 || viewport.width < 1 || viewport.height < 1) {
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
    const [left, top] = project(rect[0], rect[1]);
    const [right, bottom] = project(rect[2], rect[3]);

    return [left, top, right, bottom];
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

function mountFullscreen(stage, onRefit) {
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
            requestAnimationFrame(() => onRefit());
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
                // browser refuses the Fullscreen API.
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
            We couldn't load the magazine in this browser. Try the PDF
            or the "Read as text" view below.
        </p>
    `;
}
