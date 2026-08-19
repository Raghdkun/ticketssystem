import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { toast } from 'sonner';
import type { FlashToast } from '@/types/ui';

type FlashProps = { flash?: { toast?: FlashToast | null } };

/**
 * Surfaces server flash messages as toasts.
 *
 * Must be called from inside the Inertia tree: usePage throws outside it, and
 * the Toaster itself is mounted as a sibling of the app.
 */
export function useFlashToast(): void {
    const { flash } = usePage<FlashProps>().props;
    const message = flash?.toast?.message ?? null;
    const type = flash?.toast?.type ?? 'success';
    const lastShown = useRef<string | null>(null);

    useEffect(() => {
        if (!message) {
            // Let an identical message show again after a later action.
            lastShown.current = null;

            return;
        }

        const signature = `${type}:${message}`;

        if (lastShown.current === signature) {
            return;
        }

        lastShown.current = signature;
        toast[type](message);
    }, [message, type]);
}
