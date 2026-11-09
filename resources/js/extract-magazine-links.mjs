/**
 * Reads link annotations and text positions from a magazine PDF.
 * The Laravel job stores the result; this stays out of the browser.
 *
 * Usage: node resources/js/extract-magazine-links.mjs <file.pdf>
 */
import { readFileSync } from 'node:fs';
import { getDocument } from 'pdfjs-dist/legacy/build/pdf.mjs';

const file = process.argv[2];

if (!file) {
    console.error('Missing PDF path');
    process.exit(1);
}

const data = new Uint8Array(readFileSync(file));
const pdf = await getDocument({
    data,
    disableWorker: true,
    isEvalSupported: false,
    useSystemFonts: true,
}).promise;

const pages = [];

for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber += 1) {
    const page = await pdf.getPage(pageNumber);
    const viewport = page.getViewport({ scale: 1 });
    const annotations = [];

    for (const annotation of await page.getAnnotations()) {
        if (annotation?.subtype !== 'Link' || !Array.isArray(annotation.rect)) {
            continue;
        }

        const href = annotation.url || annotation.unsafeUrl;

        if (typeof href !== 'string' || href.trim() === '') {
            continue;
        }

        const [x1, y1, x2, y2] = project(annotation.rect, viewport.transform);
        const left = Math.min(x1, x2);
        const top = Math.min(y1, y2);
        const width = Math.abs(x2 - x1);
        const height = Math.abs(y2 - y1);

        if (width < 1 || height < 1) {
            continue;
        }

        annotations.push({
            url: href,
            x: round((left / viewport.width) * 100),
            y: round((top / viewport.height) * 100),
            w: round((width / viewport.width) * 100),
            h: round((height / viewport.height) * 100),
        });
    }

    const content = await page.getTextContent();
    const items = [];

    for (const item of content.items) {
        if (!item?.str || !Array.isArray(item.transform) || item.str.trim() === '') {
            continue;
        }

        const [a, b, c, d, e, f] = item.transform;
        const fontSize = Math.max(Math.hypot(c, d), Math.hypot(a, b), 8);
        const width = item.width || item.str.length * fontSize * 0.5;
        const top = viewport.height - f - fontSize;

        items.push({
            text: item.str,
            x: round((e / viewport.width) * 100),
            y: round((top / viewport.height) * 100),
            w: round((width / viewport.width) * 100),
            h: round((fontSize / viewport.height) * 100),
            baseline: round(f),
        });
    }

    pages.push({
        width: viewport.width,
        height: viewport.height,
        annotations,
        items,
    });
}

process.stdout.write(JSON.stringify({ pages }));

function project(rect, transform) {
    const [a, b, c, d, e, f] = transform;
    const point = (x, y) => [a * x + c * y + e, b * x + d * y + f];
    const [left, top] = point(rect[0], rect[1]);
    const [right, bottom] = point(rect[2], rect[3]);

    return [left, top, right, bottom];
}

function round(value) {
    return Math.round(value * 100) / 100;
}
