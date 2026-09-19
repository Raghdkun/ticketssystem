import { CheckCircle2, Clock, UserX, XCircle } from 'lucide-react';
import { motion, useReducedMotion } from 'motion/react';
import { cn } from '@/lib/utils';
import type { TicketStatus } from '@/types/public';

// The same five status tokens the pills use, so the banner on a ticket and
// the badge on a report agree about what "paid" looks like.
const styles: Record<TicketStatus, string> = {
    pending: 'bg-status-pending-bg text-status-pending-fg',
    paid: 'bg-status-paid-bg text-status-paid-fg',
    cancelled: 'bg-status-danger-bg text-status-danger-fg',
    expired: 'bg-status-draft-bg text-status-draft-fg',
    no_show: 'bg-status-draft-bg text-status-draft-fg',
};

const icons: Record<TicketStatus, typeof Clock> = {
    pending: Clock,
    paid: CheckCircle2,
    cancelled: XCircle,
    expired: XCircle,
    no_show: UserX,
};

export function StatusBanner({
    status,
    label,
    pulse,
}: {
    status: TicketStatus;
    label: string;
    pulse: boolean;
}) {
    const reduceMotion = useReducedMotion();
    const Icon = icons[status];

    return (
        <motion.div
            key={status}
            layout
            initial={reduceMotion ? false : { y: -6 }}
            animate={{
                y: 0,
                scale: pulse && !reduceMotion ? [1, 1.06, 1] : 1,
            }}
            transition={{ duration: reduceMotion ? 0 : 0.45 }}
            className={cn(
                'flex items-center justify-center gap-2 rounded-md py-3 text-sm font-extrabold',
                styles[status],
            )}
        >
            <Icon className="size-5" />
            {label}
        </motion.div>
    );
}
