import { Link, usePage } from '@inertiajs/react';
import { CalendarDays, LayoutGrid, ScanLine, Search } from 'lucide-react';
import EventController from '@/actions/App/Http/Controllers/Owner/EventController';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useTranslation } from '@/lib/translation';
import { cn, toUrl } from '@/lib/utils';
import { dashboard } from '@/routes';
import { scan, search } from '@/routes/owner';

/**
 * Primary navigation on a phone.
 *
 * The sidebar is a desktop pattern: on a phone it costs a tap to open before
 * you can go anywhere, and an owner working a door needs the scanner in one
 * reach. Four destinations only — a tab bar that scrolls has stopped being a
 * tab bar.
 */
export function BottomTabBar() {
    const t = useTranslation();
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const page = usePage<{ auth: { user: { id: number } | null } }>();

    if (!page.props.auth?.user) {
        return null;
    }

    const tabs = [
        { href: dashboard(), icon: LayoutGrid, label: 'dash.title' },
        {
            href: EventController.index(),
            icon: CalendarDays,
            label: 'owner.events',
        },
        { href: scan(), icon: ScanLine, label: 'owner.scan' },
        { href: search(), icon: Search, label: 'common.search' },
    ];

    return (
        <nav
            aria-label={t('common.platform')}
            /* pb-safe keeps the row clear of the iOS home indicator. */
            className="fixed inset-x-0 bottom-0 z-40 border-t border-sidebar-border bg-sidebar/95 pb-[env(safe-area-inset-bottom)] backdrop-blur md:hidden"
        >
            <ul className="mx-auto flex max-w-lg items-stretch">
                {tabs.map((tab) => {
                    const active = isCurrentOrParentUrl(toUrl(tab.href));

                    return (
                        <li key={tab.label} className="flex-1">
                            <Link
                                href={tab.href}
                                aria-current={active ? 'page' : undefined}
                                className={cn(
                                    'flex min-h-14 cursor-pointer flex-col items-center justify-center gap-1 px-1 py-2 text-[11px] font-medium transition-colors',
                                    active
                                        ? 'text-primary'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                <tab.icon
                                    className="size-5"
                                    /* Colour alone is not the signal. */
                                    strokeWidth={active ? 2.4 : 1.8}
                                />
                                <span className="truncate">{t(tab.label)}</span>
                            </Link>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}
