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

const footerNavItems: NavItem[] = [];

export function AppSidebar() {
    // Platform administration is only reachable by super admins; the server
    // enforces this, the nav item just avoids showing a dead end.
    const isSuperAdmin = useIsSuperAdmin();

    const navItems: NavItem[] = isSuperAdmin
        ? [
              ...mainNavItems,
              { title: 'admin.title', href: owners(), icon: ShieldCheck },
              {
                  title: 'admin.settings',
                  href: platformSettings(),
                  icon: SlidersHorizontal,
              },
          ]
        : mainNavItems;

    return (
        <Sidebar collapsible="icon" variant="inset">
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
                <NavMain items={navItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
