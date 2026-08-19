import { router, usePage } from '@inertiajs/react';
import { ShieldAlert } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/lib/translation';

type Props = {
    auth: { impersonating: { name: string | null } | null };
};

/**
 * Persistent warning shown while a super admin is acting as another user.
 *
 * Impersonation here is full access, so anything done is indistinguishable
 * from the owner's own actions in the ticket history. The banner is sticky and
 * high-contrast on purpose: forgetting you are impersonating is the failure
 * mode that causes real damage.
 */
export function ImpersonationBanner() {
    const { auth } = usePage<Props>().props;
    const t = useTranslation();

    if (!auth?.impersonating) {
        return null;
    }

    return (
        <div
            role="status"
            className="sticky top-0 z-50 flex flex-wrap items-center justify-center gap-3 bg-amber-500 px-4 py-2 text-sm font-medium text-amber-950"
        >
            <span className="inline-flex items-center gap-2">
                <ShieldAlert className="size-4" />
                {t('admin.impersonating_banner', {
                    name: auth.impersonating.name ?? '',
                })}
            </span>

            <Button
                type="button"
                size="sm"
                variant="outline"
                className="h-8 cursor-pointer border-amber-900/40 bg-amber-100 text-amber-950 hover:bg-amber-50"
                onClick={() => router.post('/impersonation/stop')}
            >
                {t('admin.stop_impersonating')}
            </Button>
        </div>
    );
}
