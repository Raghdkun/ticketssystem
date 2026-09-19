import { usePage } from '@inertiajs/react';
import { Languages } from 'lucide-react';
import { useLocale } from '@/lib/locale';
import { useTranslation } from '@/lib/translation';
import { cn } from '@/lib/utils';

/**
 * Switches locale via a query parameter the server persists to the session.
 * A plain link rather than an Inertia visit, so the document's lang and dir
 * attributes are re-rendered along with it.
 *
 * The current path comes from Inertia, not `window.location`: the server
 * render has no window, so reading it there produced `/?lang=en` on every
 * page and a hydration mismatch on every page load.
 */
export function LanguageToggle({ className }: { className?: string }) {
    const { locale } = useLocale();
    const { url: current } = usePage();
    const t = useTranslation();
    const next = locale === 'ar' ? 'en' : 'ar';

    const url = new URL(current, 'http://localhost');
    url.searchParams.set('lang', next);

    return (
        <a
            href={`${url.pathname}${url.search}`}
            className={cn(
                'inline-flex min-h-11 cursor-pointer items-center gap-1.5 rounded-lg bg-black/40 px-3 py-1.5 text-xs font-medium text-white backdrop-blur transition-colors duration-200 hover:bg-black/60',
                className,
            )}
        >
            <Languages className="size-3.5" />
            {t('common.language')}
        </a>
    );
}
