import { useEffect, useState } from 'react';
import { useTranslation } from '@/lib/translation';

/**
 * Time left before an unpaid hold lapses and the seats go back on sale.
 *
 * A fixed timestamp ("pay before 19 Aug, 05:43") asks the reader to do the
 * arithmetic. A countdown states the thing they actually want to know, and it
 * is the difference between a deadline that feels abstract and one that feels
 * real.
 */
function remaining(expiresAt: string) {
    const ms = new Date(expiresAt).getTime() - Date.now();

    return {
        expired: ms <= 0,
        hours: Math.max(0, Math.floor(ms / 3_600_000)),
        minutes: Math.max(0, Math.floor((ms % 3_600_000) / 60_000)),
    };
}

export function HoldCountdown({
    expiresAt,
    amount,
}: {
    expiresAt: string;
    amount: string;
}) {
    const t = useTranslation();
    const [left, setLeft] = useState(() => remaining(expiresAt));

    useEffect(() => {
        // Derived from the string rather than a Date built during render: a
        // fresh object each render would make the effect re-run every time.
        // Half a minute is enough, since the value is only read to the minute
        // and a per-second tick would wake the device for nothing.
        const id = setInterval(() => setLeft(remaining(expiresAt)), 30_000);

        return () => clearInterval(id);
    }, [expiresAt]);

    if (left.expired) {
        return null;
    }

    return (
        <div className="rounded-xl bg-brand-cta/15 p-4 text-center">
            <p className="font-display text-2xl font-semibold text-[#8a5a0c] tabular-nums dark:text-[#f3c766]">
                {t('ticket.time_left', {
                    hours: left.hours,
                    minutes: left.minutes,
                })}
            </p>
            <p className="mt-1 text-xs text-muted-foreground">
                {t('ticket.pay_or_released', { amount })}
            </p>
        </div>
    );
}
