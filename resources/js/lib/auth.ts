import { usePage } from '@inertiajs/react';

type AuthUser = {
    id: number;
    name: string;
    is_super_admin?: boolean;
    door_staff_for?: number | null;
};

type AuthProps = { auth: { user: AuthUser | null } };

/** The signed-in account, or null. Named as a hook because it calls one. */
function useAuthUser(): AuthUser | null {
    return usePage<AuthProps>().props.auth?.user ?? null;
}

/**
 * Whether the signed-in account administers the platform.
 *
 * Presentation only — every admin route is gated server-side regardless of
 * what the client believes.
 */
export function useIsSuperAdmin(): boolean {
    return useAuthUser()?.is_super_admin === true;
}

/**
 * Whether this account only works a venue's door.
 *
 * Used to trim the sidebar to what they can actually reach; the middleware is
 * what actually keeps them out.
 */
export function useIsDoorStaff(): boolean {
    const current = useAuthUser();

    return (
        current?.door_staff_for !== null &&
        current?.door_staff_for !== undefined
    );
}
