import { usePage } from '@inertiajs/react';

type AuthProps = {
    auth: { user: { id: number; name: string; role?: string } | null };
};

/**
 * Whether the signed-in user is a platform super admin. Presentation only —
 * every admin route is gated server-side regardless of what the client thinks.
 */
export function useIsSuperAdmin(): boolean {
    const { auth } = usePage<AuthProps>().props;

    return auth?.user?.role === 'super_admin';
}
