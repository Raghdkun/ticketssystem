import { useTranslation } from '@/lib/translation';
import { cn } from '@/lib/utils';
import type { TicketStatus } from '@/types/public';

/**
 * Ticket status.
 *
 * A dot plus text, never colour alone — the door is lit badly and some of the
 * people reading this are colourblind, so hue is reinforcement rather than the
 * signal. Paid takes the brand jade: the good outcome is the brand.
 */
const tones: Record<TicketStatus, { pill: string; dot: string }> = {
    paid: {
        pill: 'bg-[#ddece5] text-[#06392c] dark:bg-[#16342b] dark:text-[#ddece5]',
        dot: 'bg-[#0a5c49] dark:bg-[#4fcba5]',
    },
    pending: {
        pill: 'bg-[#fbebce] text-[#8a5a0c] dark:bg-[#3a2c11] dark:text-[#f3c766]',
        dot: 'bg-[#c88414] dark:bg-[#e8a72b]',
    },
    cancelled: {
        pill: 'bg-[#f6ded8] text-[#8a2c17] dark:bg-[#3a1d15] dark:text-[#e0a294]',
        dot: 'bg-[#a3341f] dark:bg-[#e0674c]',
    },
    expired: {
        pill: 'bg-[#ede5d8] text-[#6e675a] dark:bg-[#262218] dark:text-[#a8a091]',
        dot: 'bg-[#8a8272]',
    },
    no_show: {
        pill: 'bg-[#f2ece2] text-[#3b362d] dark:bg-[#262218] dark:text-[#d6c9b3]',
        // Hollow, so "nobody came" reads as absence rather than another colour.
        dot: 'border-2 border-[#6e675a] bg-transparent dark:border-[#a8a091]',
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
                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium whitespace-nowrap',
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
