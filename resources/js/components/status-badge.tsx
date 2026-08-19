import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/lib/translation';
import { cn } from '@/lib/utils';
import type { TicketStatus } from '@/types/public';

const tones: Record<TicketStatus, string> = {
    pending:
        'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200',
    paid: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
    cancelled: 'bg-red-100 text-red-900 dark:bg-red-950 dark:text-red-200',
    expired:
        'bg-neutral-200 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300',
    no_show:
        'bg-orange-100 text-orange-900 dark:bg-orange-950 dark:text-orange-200',
};

/** Translated, colour-coded ticket status. Replaces raw enum values in the UI. */
export function StatusBadge({
    status,
    className,
}: {
    status: TicketStatus;
    className?: string;
}) {
    const t = useTranslation();

    return (
        <Badge
            variant="secondary"
            className={cn('border-0 font-medium', tones[status], className)}
        >
            {t(`ticket.status.${status}`)}
        </Badge>
    );
}
