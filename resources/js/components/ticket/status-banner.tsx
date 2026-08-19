import { CheckCircle2, Clock, UserX, XCircle } from 'lucide-react';
import { motion, useReducedMotion } from 'motion/react';
import { cn } from '@/lib/utils';
import type { TicketStatus } from '@/types/public';

const styles: Record<TicketStatus, string> = {
    pending:
        'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200',
    paid: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
    cancelled: 'bg-red-100 text-red-900 dark:bg-red-950 dark:text-red-200',
    expired:
        'bg-neutral-200 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300',
    no_show:
        'bg-orange-100 text-orange-900 dark:bg-orange-950 dark:text-orange-200',
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
                'flex items-center justify-center gap-2 rounded-xl py-3 text-sm font-semibold',
                styles[status],
            )}
        >
            <Icon className="size-5" />
            {label}
        </motion.div>
    );
}
