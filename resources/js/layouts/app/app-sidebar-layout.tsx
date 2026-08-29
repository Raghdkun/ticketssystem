import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { useTranslation } from '@/lib/translation';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    const t = useTranslation();

    /*
     * The page's own heading, for assistive technology.
     *
     * Every page on the authenticated side builds its visible heading from
     * <Heading>, which renders an h2 -- so the whole owner and admin side had
     * no h1 at all, and a screen reader had nothing to announce the page as.
     * Taking it from the last breadcrumb keeps it correct without touching
     * twenty pages, and cannot drift from what the page actually is.
     *
     * Breadcrumb titles are translation keys, resolved here rather than in
     * the static page config where hooks cannot run.
     */
    const heading = breadcrumbs.at(-1)?.title;

    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" className="overflow-x-hidden">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {heading && <h1 className="sr-only">{t(heading)}</h1>}
                {children}
            </AppContent>
        </AppShell>
    );
}
