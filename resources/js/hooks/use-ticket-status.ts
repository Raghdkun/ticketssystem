import { useEchoPublic } from '@laravel/echo-react';
import { useEffect, useRef, useState } from 'react';
import type { TicketStatus } from '@/types/public';

type StatusPayload = {
    token: string;
    status: TicketStatus;
    verified_at: string | null;
};

type LiveStatus = {
    status: TicketStatus;
    verifiedAt: string | null;
    /** True briefly after a live change, so animations only fire on real flips. */
    justChanged: boolean;
};

/** How long the "just changed" flag stays raised, in milliseconds. */
const FLASH_MS = 2000;

/**
 * Live ticket status over Reverb.
 *
 * The channel is keyed by the ticket's token rather than a user id, because
 * ticket holders are anonymous and have nothing else to authorise against.
 *
 * Live updates are held separately and layered over the server-rendered props
 * rather than copied into state, so there is no prop-to-state sync effect.
 * Ticket statuses only ever move towards a terminal value, so preferring the
 * live value is always at least as fresh as the rendered one.
 */
export function useTicketStatus(
    token: string,
    initialStatus: TicketStatus,
    initialVerifiedAt: string | null,
): LiveStatus {
    const [live, setLive] = useState<StatusPayload | null>(null);
    const [justChanged, setJustChanged] = useState(false);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEchoPublic<StatusPayload>(
        `ticket.${token}`,
        '.status.changed',
        (payload) => {
            setLive(payload);
            setJustChanged(true);

            if (timer.current) {
                clearTimeout(timer.current);
            }

            timer.current = setTimeout(() => setJustChanged(false), FLASH_MS);
        },
    );

    useEffect(
        () => () => {
            if (timer.current) {
                clearTimeout(timer.current);
            }
        },
        [],
    );

    return {
        status: live?.status ?? initialStatus,
        verifiedAt: live?.verified_at ?? initialVerifiedAt,
        justChanged,
    };
}
