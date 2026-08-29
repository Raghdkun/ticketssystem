import { Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { useTranslation } from '@/lib/translation';

type Props = { platform: { name: string } };

/**
 * Public footer.
 *
 * Every public page previously ended in nothing, which leaves a visitor at a
 * dead end. This gives them a way back, the venue-facing entry point, and the
 * legal links a public site is expected to carry.
 */
export function PublicFooter() {
    const { platform } = usePage<Props>().props;
    const t = useTranslation();
    const year = new Date().getFullYear();

    return (
        <footer className="mt-16 border-t">
            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 p-6 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-2.5">
                    <AppLogoIcon
                        detail="compact"
                        className="size-6 text-primary"
                    />
                    <span className="text-sm font-semibold">
                        {platform?.name}
                    </span>
                </div>

                {/* A nav row, not prose, so the coarse-pointer floor applies
                    to each link. Scoped to coarse pointers: on a desktop these
                    are text links and 44px of height would just be padding. */}
                <nav className="flex flex-wrap items-center gap-x-5 text-sm text-muted-foreground">
                    <Link
                        href="/my-tickets"
                        className="inline-flex cursor-pointer items-center transition-colors hover:text-foreground coarse:min-h-11"
                    >
                        {t('ticket.my_tickets')}
                    </Link>
                    <Link
                        href="/privacy"
                        className="inline-flex cursor-pointer items-center transition-colors hover:text-foreground coarse:min-h-11"
                    >
                        {t('legal.privacy')}
                    </Link>
                    <Link
                        href="/terms"
                        className="inline-flex cursor-pointer items-center transition-colors hover:text-foreground coarse:min-h-11"
                    >
                        {t('legal.terms')}
                    </Link>
                    <Link
                        href="/login"
                        className="inline-flex cursor-pointer items-center transition-colors hover:text-foreground coarse:min-h-11"
                    >
                        {t('legal.for_venues')}
                    </Link>
                </nav>

                <p className="text-xs text-muted-foreground">© {year}</p>
            </div>
        </footer>
    );
}
