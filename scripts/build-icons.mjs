/**
 * Rasterise the brand marks into every icon the app ships.
 *
 * Run with `npm run icons`. The SVGs in resources/brand are the source of
 * truth; nothing here should be edited by hand, and nothing in public/ should
 * be edited instead of here.
 *
 * Two marks, two jobs. The wordmark (ناس, outlined Lifta Black) is the brand;
 * the orange disc exists for the places a wordmark cannot go — a 16px tab, a
 * home screen, a notification badge. Anything under `public/icons/` is cached
 * by the service worker without revalidation, so a change here needs the
 * VERSION bump in public/sw.js in the same commit.
 */
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { Resvg } from '@resvg/resvg-js';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const read = (p) => readFileSync(resolve(root, p), 'utf8');

const ORANGE = '#F66002';
const INK = '#0D0E0F';
const CREAM = '#F6F1EA';

const disc = read('resources/brand/nas-disc.svg');
const wordmarkSvg = read('resources/brand/nas-wordmark.svg');

// The wordmark's path and its coordinate space, lifted from the source file so
// the geometry is never duplicated by hand.
const WORDMARK = {
    d: wordmarkSvg.match(/ d="([^"]+)"/)[1],
    translate: wordmarkSvg.match(/translate\(([^)]+)\)/)[1],
    width: 288.928,
    height: 182.8,
};

/**
 * The wordmark, drawn into a box of the given size at a given fill.
 *
 * `fit` is the fraction of the box the wordmark spans horizontally; the
 * vertical position follows from keeping the wordmark's own aspect ratio.
 */
function wordmark({ box, fill, fit, background }) {
    const width = box.w * fit;
    const scale = width / WORDMARK.width;
    const height = WORDMARK.height * scale;
    const x = (box.w - width) / 2;
    const y = (box.h - height) / 2;

    const bg = background
        ? `<rect width="${box.w}" height="${box.h}" fill="${background}"/>`
        : '';

    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${box.w} ${box.h}">${bg}<g transform="translate(${x} ${y}) scale(${scale}) translate(${WORDMARK.translate})"><path d="${WORDMARK.d}" fill="${fill}"/></g></svg>`;
}

function png(svg, size) {
    return new Resvg(svg, {
        fitTo: { mode: 'width', value: size },
        background: 'rgba(0,0,0,0)',
    })
        .render()
        .asPng();
}

/**
 * ICO, hand-built. The format has allowed PNG-encoded entries since Vista, so
 * a container plus one PNG per size is the whole file — and avoids a
 * dependency whose only job would be concatenating six buffers.
 */
function ico(sizes) {
    const images = sizes.map((size) => ({ size, data: png(disc, size) }));

    const header = Buffer.alloc(6);
    header.writeUInt16LE(0, 0); // reserved
    header.writeUInt16LE(1, 2); // type: icon
    header.writeUInt16LE(images.length, 4);

    let offset = 6 + images.length * 16;
    const entries = images.map(({ size, data }) => {
        const entry = Buffer.alloc(16);
        entry.writeUInt8(size >= 256 ? 0 : size, 0); // 0 means 256
        entry.writeUInt8(size >= 256 ? 0 : size, 1);
        entry.writeUInt8(0, 2); // palette
        entry.writeUInt8(0, 3); // reserved
        entry.writeUInt16LE(1, 4); // colour planes
        entry.writeUInt16LE(32, 6); // bits per pixel
        entry.writeUInt32LE(data.length, 8);
        entry.writeUInt32LE(offset, 12);
        offset += data.length;

        return entry;
    });

    return Buffer.concat([header, ...entries, ...images.map((i) => i.data)]);
}

const out = (p, data) => {
    const target = resolve(root, p);
    mkdirSync(dirname(target), { recursive: true });
    writeFileSync(target, data);
    console.log(`  ${p}  ${(data.length / 1024).toFixed(1)} KB`);
};

console.log('Building icons from resources/brand/…');

// The favicon is the disc: a tab renders it at 16px against an unknown
// background, where an orange circle reads and three Arabic letters do not.
out('public/favicon.svg', disc);
out('public/favicon.ico', ico([16, 32, 48]));

// `purpose: any` icons keep the disc's own transparent corners.
out('public/icons/icon-192.png', png(disc, 192));
out('public/icons/icon-512.png', png(disc, 512));

// A maskable icon is cropped to whatever shape the launcher likes, so it is
// the wordmark on a full-bleed orange field, held inside the 80% safe zone.
const tile = wordmark({
    box: { w: 512, h: 512 },
    fill: INK,
    fit: 0.72,
    background: ORANGE,
});
out('public/icons/icon-maskable-512.png', png(tile, 512));

// iOS applies its own mask and does not honour transparency, so this one is
// the same opaque tile.
out('public/apple-touch-icon.png', png(tile, 180));

// Android draws a notification badge from the alpha channel alone and paints
// it in the system colour, so the badge is the wordmark in solid white on
// nothing. The colour icon would flatten to an unreadable blob.
out(
    'public/icons/badge-96.png',
    png(
        wordmark({ box: { w: 96, h: 96 }, fill: '#FFFFFF', fit: 0.78 }),
        96,
    ),
);

// The share card for any page with no cover of its own.
const og = wordmark({
    box: { w: 1200, h: 630 },
    fill: INK,
    fit: 0.34,
    background: CREAM,
}).replace(
    '</svg>',
    `<rect x="560" y="470" width="80" height="6" rx="3" fill="${ORANGE}"/></svg>`,
);
out('public/og-default.png', png(og, 1200));

console.log('Done.');
