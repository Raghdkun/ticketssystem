import { usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import {
    WORDMARK_PATH,
    WORDMARK_TRANSLATE,
    WORDMARK_VIEWBOX,
} from './wordmark-path';

/**
 * ناس — the wordmark.
 *
 * Lifta Black outlined to paths by the designer, so it renders without the
 * font and is never typed. Fill is `currentColor`: ink on cream, cream on ink,
 * set by the caller's text colour. Height is the caller's too; width follows
 * from the aspect ratio.
 *
 * It is Arabic text. It is never mirrored for direction, which is why there
 * is no `useLocale` in here — the old mark flipped in RTL, and this one must
 * not.
 */
export function Wordmark({
    className,
    label,
    decorative = false,
    animate = false,
}: {
    className?: string;
    /** Accessible name; defaults to the platform name. */
    label?: string;
    /** Hide from assistive technology when the name is already beside it. */
    decorative?: boolean;
    /** Rise into place on mount — auth and landing screens only. */
    animate?: boolean;
}) {
    const { name } = usePage<{ name: string }>().props;

    return (
        <svg
            viewBox={WORDMARK_VIEWBOX}
            role={decorative ? undefined : 'img'}
            aria-label={decorative ? undefined : (label ?? name)}
            aria-hidden={decorative || undefined}
            className={cn(
                'block h-8 w-auto fill-current',
                animate && 'brand-rise',
                className,
            )}
        >
            <g transform={`translate(${WORDMARK_TRANSLATE})`}>
                <path d={WORDMARK_PATH} />
            </g>
        </svg>
    );
}
