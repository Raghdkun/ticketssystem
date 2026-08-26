import { Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight } from 'lucide-react';
import { useLocale } from '@/lib/locale';
import { useTranslation } from '@/lib/translation';
import { cn } from '@/lib/utils';

/**
 * A way back, for pages that have no other chrome offering one.
 *
 * The arrow is chosen rather than mirrored: "back" points left in English and
 * right in Arabic, and a CSS rotation would also flip the glyph's stroke caps.
 */
export function BackLink({
    href,
    label,
    className,
}: {
    href: string;
    /** Translation key. Defaults to a plain "Back". */
    label?: string;
    className?: string;
}) {
    const t = useTranslation();
    const { direction } = useLocale();
    const Arrow = direction === 'rtl' ? ArrowRight : ArrowLeft;

    return (
        <Link
            href={href}
            className={cn(
                'inline-flex min-h-11 cursor-pointer items-center gap-1.5 rounded-lg text-sm text-muted-foreground transition-colors hover:text-foreground',
                className,
            )}
        >
            <Arrow className="size-4 shrink-0" />
            {t(label ?? 'common.back')}
        </Link>
    );
}
