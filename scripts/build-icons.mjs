/**
 * Rasterise the brand marks into every icon the app ships.
 *
 * Run with `npm run icons`. The SVGs in resources/brand are the source of
 * truth; nothing here should be edited by hand, and nothing in public/ should
 * be edited instead of here.
 */
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { Resvg } from '@resvg/resvg-js';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const read = (p) => readFileSync(resolve(root, p), 'utf8');

const icon = read('resources/brand/icon.svg');
const iconMaskable = read('resources/brand/icon-maskable.svg');

/**
 * Rounded tile, for icons composited onto an arbitrary background.
 *
 * Both tile rects are rounded, not just the base one: the sheen sits on top,
 * so leaving it square paints a bright wedge outside the corner radius.
 */
function rounded(svg, radius) {
    return svg.replaceAll(
        '<rect width="48" height="48"',
        `<rect width="48" height="48" rx="${radius}"`,
    );
}

const PASS =
    'M12 10h14.5a3.5 3.5 0 0 0 7 0H36a6 6 0 0 1 6 6v16a6 6 0 0 1-6 6h-2.5a3.5 3.5 0 0 0-7 0H12a6 6 0 0 1-6-6V16a6 6 0 0 1 6-6Z';

/**
 * The mark, simplified for the size it will actually be seen at.
 *
 * The artboard is explicit about this: dots drop below 48px. Rendering the
 * full drawing into a 16px favicon turns the perforation into mush, so each
 * raster gets the version designed for it.
 */
function markAt(size) {
    const seat = size <= 16 ? 6 : 4.6;
    const parts = [`<path d="${PASS}" fill="#0A5C49"/>`];

    if (size >= 48) {
        parts.push(
            '<path d="M30 16v17" stroke="#FAF7F2" stroke-width="2.6" stroke-linecap="round" stroke-dasharray="0 5.4"/>',
        );
    }

    parts.push(`<circle cx="18.5" cy="24" r="${seat}" fill="#FAF7F2"/>`);

    if (size > 16) {
        parts.push('<circle cx="36" cy="24" r="3.2" fill="#E8A72B"/>');
    }

    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" fill="none">${parts.join('')}</svg>`;
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
    const images = sizes.map((size) => ({
        size,
        data: png(markAt(size), size),
    }));

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

// The favicon is the bare mark, never the tile: a tab renders it small and
// against an unknown background, where a coloured square says nothing. The
// SVG carries the 32px reading — browsers scale it up cleanly, and detail
// that only works large does not scale down.
out('public/favicon.svg', markAt(32));

out('public/icons/icon-192.png', png(rounded(icon, 10), 192));
out('public/icons/icon-512.png', png(rounded(icon, 10), 512));
out('public/icons/icon-maskable-512.png', png(iconMaskable, 512));

// iOS applies its own mask and does not honour transparency, so this one is
// square and fully opaque.
out('public/apple-touch-icon.png', png(icon, 180));

out('public/favicon.ico', ico([16, 32, 48]));

console.log('Done.');
