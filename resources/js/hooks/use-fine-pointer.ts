import { useSyncExternalStore } from 'react';

const QUERY = '(pointer: fine)';

function subscribe(onChange: () => void): () => void {
    if (typeof window === 'undefined') {
        return () => {};
    }

    const list = window.matchMedia(QUERY);
    list.addEventListener('change', onChange);

    return () => list.removeEventListener('change', onChange);
}

/**
 * Whether the primary pointer is precise -- a mouse or trackpad.
 *
 * Pointer-following effects (a button that leans toward the cursor, a
 * spotlight under it) are meaningless on a touch screen and are switched
 * off there. False on the server, so the first paint never assumes a mouse.
 */
export function useFinePointer(): boolean {
    return useSyncExternalStore(
        subscribe,
        () => window.matchMedia(QUERY).matches,
        () => false,
    );
}
