const STORAGE_KEY = 'my-tickets';

export type StoredTicket = {
    token: string;
    title: string;
    savedAt: string;
};

/**
 * Ticket links are kept in localStorage so a visitor who closes the tab can
 * still find their ticket without us paying for an SMS.
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
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        const parsed: unknown = raw ? JSON.parse(raw) : [];

        return Array.isArray(parsed) ? (parsed as StoredTicket[]) : [];
    } catch {
        return [];
    }
}
