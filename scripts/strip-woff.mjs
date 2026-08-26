/**
 * Drop the `.woff` half of every font face from the built stylesheets.
 *
 * The font plugin emits two separate `@font-face` rules per face — one woff2,
 * one woff — with identical family, weight, style and unicode-range. They are
 * not a fallback list inside one `src`, so the later rule wins: the browser
 * downloads the *woff*, while the `<link rel=preload>` tags point at a woff2
 * nothing ends up using.
 *
 * Measured on the landing page before this ran: 311KB of woff2 preloaded and
 * discarded, on top of 121KB of woff actually fetched.
 *
 * woff2 has been supported by every browser this app already requires — ES
 * modules, service workers, custom properties — for close to a decade, so the
 * woff rules buy nothing at all.
 *
 * Runs after `vite build` rather than as a plugin hook: the stylesheet is
 * written to disk by the fonts plugin, not emitted as a rollup asset, so it is
 * not in the bundle for a plugin to rewrite.
 */
import { readFileSync, readdirSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = resolve(
    dirname(fileURLToPath(import.meta.url)),
    '..',
    'public/build/assets',
);

let stripped = 0;
let saved = 0;

for (const name of readdirSync(dir)) {
    if (!name.endsWith('.css')) {
        continue;
    }

    const file = resolve(dir, name);
    const before = readFileSync(file, 'utf8');

    if (!before.includes('format("woff")')) {
        continue;
    }

    const after = before.replace(
        /@font-face\s*\{[^}]*format\("woff"\)[^}]*\}\s*/g,
        '',
    );

    writeFileSync(file, after);

    stripped += (before.match(/format\("woff"\)/g) ?? []).length;
    saved += before.length - after.length;
}

console.log(
    stripped > 0
        ? `woff2-only: removed ${stripped} woff faces (${(saved / 1024).toFixed(1)} KB of CSS)`
        : 'woff2-only: nothing to strip',
);
