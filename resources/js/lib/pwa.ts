/** Register the service worker. Safe to call on every page load. */
export function registerServiceWorker(): void {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    window.addEventListener('load', () => {
        void navigator.serviceWorker.register('/sw.js').catch(() => {
            // Registration fails on insecure origins and in some private
            // modes. The site works fine without it.
        });
    });
}

export function isStandalone(): boolean {
    return (
        window.matchMedia('(display-mode: standalone)').matches ||
        // iOS Safari predates display-mode and exposes this instead.
        (window.navigator as { standalone?: boolean }).standalone === true
    );
}

export function isIos(): boolean {
    return /iphone|ipad|ipod/i.test(window.navigator.userAgent);
}
