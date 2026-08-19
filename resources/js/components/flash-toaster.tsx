import { useFlashToast } from '@/hooks/use-flash-toast';

/**
 * Renders nothing; exists so the flash hook runs inside the Inertia tree.
 * Mounted by each layout and by the standalone public pages.
 */
export function FlashToaster() {
    useFlashToast();

    return null;
}
