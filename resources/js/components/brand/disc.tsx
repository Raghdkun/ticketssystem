import { cn } from '@/lib/utils';
import { DISC_CIRCLE, DISC_PATH, DISC_VIEWBOX } from './disc-path';

/**
 * The orange disc: the wordmark on a circle, for the places a wordmark cannot
 * go — the collapsed sidebar rail, a seal on a ticket, an avatar. Favicon and
 * PWA icons are the same drawing, rasterised by `npm run icons`.
 *
 * Secondary by design. The brand is the wordmark; this is what it becomes
 * when there is only room for a circle.
 */
export function Disc({
    className,
    label,
}: {
    className?: string;
    /** Accessible name. Without one the disc is decorative. */
    label?: string;
}) {
    return (
        <svg
            viewBox={DISC_VIEWBOX}
            role={label ? 'img' : undefined}
            aria-label={label}
            aria-hidden={label ? undefined : true}
            className={cn('block size-8 shrink-0', className)}
        >
            <circle
                cx={DISC_CIRCLE.cx}
                cy={DISC_CIRCLE.cy}
                r={DISC_CIRCLE.r}
                fill="var(--brand-orange)"
            />
            <path d={DISC_PATH} fill="var(--brand-ink)" />
        </svg>
    );
}
