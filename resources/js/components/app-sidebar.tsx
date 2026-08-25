import { Link } from '@inertiajs/react';
import {
    CalendarDays,
    LayoutGrid,
    ScanLine,
    Search,
    ShieldCheck,
    SlidersHorizontal,
} from 'lucide-react';
import EventController from '@/actions/App/Http/Controllers/Owner/EventController';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useIsSuperAdmin } from '@/lib/auth';
import { useLocale } from '@/lib/locale';
import { dashboard } from '@/routes';
import { owners, settings as platformSettings } from '@/routes/admin';
import { scan, search } from '@/routes/owner';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'dash.title',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'owner.events',
        href: EventController.index(),
        icon: CalendarDays,
    },
    {
        title: 'owner.verify_title',
        href: scan(),
        icon: ScanLine,
    },
    {
        title: 'owner.search_all',
        href: search(),
        icon: Search,
    },
];

const adminNavItems: NavItem[] = [
    {
        title: 'admin.title',
        href: owners(),
        icon: ShieldCheck,
    },
    {
        title: 'admin.settings',
        href: platformSettings(),
        icon: SlidersHorizontal,
    },
];

const footerNavItems: NavItem[] = [];

export function AppSidebar() {
    const { direction } = useLocale();
    // Platform administration is only reachable by super admins; the server
    // enforces this, the nav item just avoids showing a dead end.
    const isSuperAdmin = useIsSuperAdmin();

    return (
        // The sidebar pins itself with physical left-0/right-0 from this prop,
        // while its spacer sits in document flow order. Leaving it on "left"
        // in Arabic drew the panel against the left edge but reserved the gap
        // on the right, so the content was squeezed and clipped.
        <Sidebar
            collapsible="icon"
            variant="inset"
            side={direction === 'rtl' ? 'right' : 'left'}
        >
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />

                {isSuperAdmin && (
                    <NavMain
                        items={adminNavItems}
                        label="common.administration"
                    />
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
