import { Bell, BellOff } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { pushSupport, subscribeToTicket } from '@/lib/push';
import { useTranslation } from '@/lib/translation';

/**
 * Friendly push opt-in, shown only where it can actually succeed.
 *
 * The browser's own permission dialog is never triggered until the visitor
 * asks for it here, so a refusal is a considered choice rather than a reflex
 * dismissal of a prompt they did not expect.
 */
export function PushOptIn({ token }: { token: string }) {
    const t = useTranslation();
    const [support] = useState(() =>
        typeof window === 'undefined' ? 'unconfigured' : pushSupport(),
    );
    const [state, setState] = useState<'idle' | 'working' | 'on' | 'failed'>(
        support === 'granted' ? 'on' : 'idle',
    );

    // Nothing to offer: not configured, unsupported, or already refused.
    if (
        support === 'unconfigured' ||
        support === 'unsupported' ||
        support === 'denied'
    ) {
        return null;
    }

    // Worth saying out loud: this one is our deployment's fault, not a
    // refusal, and it is invisible on a phone otherwise.
    if (support === 'insecure') {
        return (
            <p className="rounded-xl border border-dashed p-3 text-center text-xs text-muted-foreground">
                {t('perm.insecure')}
            </p>
        );
    }

    if (support === 'needs-install') {
        return (
            <p className="rounded-xl border border-dashed p-3 text-center text-xs text-muted-foreground">
                {t('push.ios_hint')}
            </p>
        );
    }

    if (state === 'on') {
        return (
            <p className="flex items-center justify-center gap-2 rounded-xl bg-muted/60 p-3 text-center text-xs text-muted-foreground">
                <Bell className="size-3.5" />
                {t('push.enabled')}
            </p>
        );
    }

    return (
        <div className="space-y-2 rounded-xl border p-4 text-center">
            <p className="text-sm font-medium">{t('push.title')}</p>
            <p className="text-xs text-muted-foreground">{t('push.body')}</p>

            <Button
                type="button"
                size="sm"
                variant="outline"
                className="w-full cursor-pointer"
                disabled={state === 'working'}
                onClick={async () => {
                    setState('working');
                    setState(
                        (await subscribeToTicket(token)) ? 'on' : 'failed',
                    );
                }}
            >
                {state === 'failed' ? <BellOff /> : <Bell />}
                {state === 'failed' ? t('push.failed') : t('push.enable')}
            </Button>
        </div>
    );
}
