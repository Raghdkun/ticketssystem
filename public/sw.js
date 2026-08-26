/*
 * Service worker for the ticket PWA.
 *
 * Deliberately conservative: ticket pages are NEVER served from cache, because
 * a stale page could show "paid" for a reservation that was since cancelled or
 * expired — or the reverse, at the venue door. Only immutable build assets and
 * icons are cached; everything else goes to the network.
 */

/*
 * BUMP THIS whenever anything under /icons/ changes.
 *
 * Files under /build/ are content-hashed, so a deploy renames them and the
 * cache misses on its own. The icons are not: `icon-192.png` keeps its name
 * forever, and this worker serves it cache-first without revalidating. Without
 * a bump, every already-installed PWA keeps the previous icon indefinitely —
 * which is exactly what would have happened to the rebrand.
 *
 * v2 — The Pass replaces the old indigo mark.
 */
const VERSION = 'v2';
const ASSET_CACHE = `assets-${VERSION}`;

self.addEventListener('install', () => {
    // Take over as soon as the new worker is ready.
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        (async () => {
            const names = await caches.keys();

            await Promise.all(
                names
                    .filter((name) => name !== ASSET_CACHE)
                    .map((name) => caches.delete(name)),
            );

            await self.clients.claim();
        })(),
    );
});

/** Only content-hashed build output and static icons are safe to cache. */
function isImmutableAsset(url) {
    return (
        url.origin === self.location.origin &&
        (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/'))
    );
}

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (!isImmutableAsset(url)) {
        // Ticket and event pages always come from the network so status is
        // never stale. Without a handler the browser uses its default path.
        return;
    }

    event.respondWith(
        (async () => {
            const cache = await caches.open(ASSET_CACHE);
            const cached = await cache.match(request);

            if (cached) {
                return cached;
            }

            const response = await fetch(request);

            if (response.ok) {
                cache.put(request, response.clone());
            }

            return response;
        })(),
    );
});
