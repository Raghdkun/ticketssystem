import { Link, router } from '@inertiajs/react';
import {
    CalendarDays,
    ClipboardCheck,
    LayoutGrid,
    Mail,
    MapPin,
    ScanLine,
    Search,
    ShieldCheck,
    Store,
    Users,
    UserCog,
    SlidersHorizontal,
} from 'lucide-react';
import { useEffect } from 'react';
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
    useSidebar,
} from '@/components/ui/sidebar';
import { useIsDoorStaff, useIsSuperAdmin } from '@/lib/auth';
import { useLocale } from '@/lib/locale';
import { dashboard } from '@/routes';
import {
    events as adminEvents,
    invitations as adminInvitations,
    owners,
    roles as adminRoles,
    settings as platformSettings,
} from '@/routes/admin';
import { scan, search } from '@/routes/owner';
import locationsRoute from '@/routes/owner/locations';
import placeRoute from '@/routes/owner/place';
import staffRoute from '@/routes/owner/staff';
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
        title: 'owner.place',
        href: placeRoute.edit(),
        icon: Store,
    },
    {
        title: 'owner.locations',
        href: locationsRoute.index(),
        icon: MapPin,
    },
    {
        title: 'staff.title',
        href: staffRoute.index(),
        icon: Users,
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
        title: 'invite.admin_title',
        href: adminInvitations(),
        icon: Mail,
    },
    {
        title: 'roles.title',
        href: adminRoles(),
        icon: UserCog,
    },
    {
        title: 'review.title',
        href: adminEvents(),
        icon: ClipboardCheck,
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
    const { isMobile, setOpenMobile } = useSidebar();
    // Door staff can only reach the door, so the rest of the list would just
    // bounce them back here.
    const doorOnly = useIsDoorStaff();

    // On a phone the sidebar is a sheet over the page, so tapping a
    // destination navigated behind it and left it sitting open on top of the
    // page it had just loaded. Closing on visit start rather than on arrival
    // lets it animate away while the next page is still in flight.
    useEffect(() => {
        if (!isMobile) {
            return;
        }

        return router.on('start', () => setOpenMobile(false));
    }, [isMobile, setOpenMobile]);

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
                <NavMain
                    items={
                        doorOnly
                            ? mainNavItems.filter((item) =>
                                  [
                                      'owner.verify_title',
                                      'owner.search_all',
                                  ].includes(item.title),
                              )
                            : mainNavItems
                    }
                />

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
