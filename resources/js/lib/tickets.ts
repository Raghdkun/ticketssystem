import { useSyncExternalStore } from 'react';

const STORAGE_KEY = 'my-tickets';

export type StoredTicket = {
    token: string;
    title: string;
    savedAt: string;
};

const EMPTY: StoredTicket[] = [];

/**
 * Cached parse of the stored list.
 *
 * useSyncExternalStore compares snapshots by reference, so parsing on every
 * call would loop forever. The cache is refreshed only when the raw string
 * actually changes.
 */
let cachedRaw: string | null = null;
let cachedValue: StoredTicket[] = EMPTY;

function parse(raw: string | null): StoredTicket[] {
    try {
        const parsed: unknown = raw ? JSON.parse(raw) : [];

        return Array.isArray(parsed) ? (parsed as StoredTicket[]) : EMPTY;
    } catch {
        return EMPTY;
    }
}

function snapshot(): StoredTicket[] {
    let raw: string | null = null;

    try {
        raw = localStorage.getItem(STORAGE_KEY);
    } catch {
        return EMPTY;
    }

    if (raw !== cachedRaw) {
        cachedRaw = raw;
        cachedValue = parse(raw);
    }

    return cachedValue;
}

function subscribe(onChange: () => void): () => void {
    // Fires when another tab writes; same-tab writes go through rememberTicket.
    window.addEventListener('storage', onChange);

    return () => window.removeEventListener('storage', onChange);
}

/**
 * Ticket links kept in localStorage so a visitor who closes the tab can still
 * find their ticket without us paying for an SMS.
 */
export function rememberTicket(ticket: StoredTicket): void {
    try {
        const existing = readTickets().filter((t) => t.token !== ticket.token);

        localStorage.setItem(
            STORAGE_KEY,
            JSON.stringify([ticket, ...existing].slice(0, 30)),
        );
    } catch {
        // Private browsing or a full quota: losing the shortcut is harmless.
    }
}

export function readTickets(): StoredTicket[] {
    return snapshot();
}

/**
 * Subscribe to the stored tickets. Returns an empty list during SSR and on
 * the first client render, then the real list once hydrated.
 */
export function useStoredTickets(): StoredTicket[] {
    return useSyncExternalStore(subscribe, snapshot, () => EMPTY);
}
