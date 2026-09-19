import { useTranslation } from '@/lib/translation';
import { cn } from '@/lib/utils';
import type { TicketStatus } from '@/types/public';

/**
 * Ticket status.
 *
 * A dot plus text, never colour alone — the door is lit badly and some of the
 * people reading this are colourblind, so hue is reinforcement rather than the
 * signal. The five tones are the design system's status tokens, so a pill
 * here and a pill in a report are the same pill.
 */
const tones: Record<TicketStatus, { pill: string; dot: string }> = {
    paid: {
        pill: 'bg-status-paid-bg text-status-paid-fg',
        dot: 'bg-status-paid-fg',
    },
    pending: {
        pill: 'bg-status-pending-bg text-status-pending-fg',
        dot: 'bg-status-pending-fg',
    },
    cancelled: {
        pill: 'bg-status-danger-bg text-status-danger-fg',
        dot: 'bg-status-danger-fg',
    },
    expired: {
        pill: 'bg-status-draft-bg text-status-draft-fg',
        dot: 'bg-status-draft-fg',
    },
    no_show: {
        pill: 'bg-status-draft-bg text-status-draft-fg',
        // Hollow, so "nobody came" reads as absence rather than another colour.
        dot: 'border-2 border-status-draft-fg bg-transparent',
    },
};

export function StatusBadge({
    status,
    className,
}: {
    status: TicketStatus;
    className?: string;
}) {
    const t = useTranslation();
    const tone = tones[status];

    return (
        <span
            className={cn(
                'inline-flex min-h-[26px] items-center gap-1.5 rounded-full px-2.5 text-xs font-extrabold whitespace-nowrap',
                tone.pill,
                className,
            )}
        >
            <span
                aria-hidden="true"
                className={cn('size-1.5 shrink-0 rounded-full', tone.dot)}
            />
            {t(`ticket.status.${status}`)}
        </span>
    );
}
