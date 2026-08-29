import { Bell, BellOff } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
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

    // Dismissal is per view rather than remembered: the card only appears on
    // a pending ticket, and once the ticket is paid it stops appearing on its
    // own. Persisting a refusal would silently outlive the reason for it.
    const [dismissed, setDismissed] = useState(false);

    if (dismissed) {
        return null;
    }

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
        <section className="brand-surface space-y-3 rounded-2xl border p-4">
            <div className="flex items-start gap-3">
                <span
                    aria-hidden
                    className="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                >
                    <Bell className="size-5" />
                </span>

                <div className="min-w-0">
                    <p className="text-sm font-semibold">{t('push.title')}</p>
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        {t('push.body')}
                    </p>
                </div>
            </div>

            {/* Said before the browser dialog appears, not after: people
                dismiss prompts they were not expecting, and a refusal here
                can only be undone in browser settings. */}
            <p className="rounded-lg bg-muted/60 p-2.5 text-xs text-muted-foreground">
                {t('push.what_happens')}
            </p>

            {state === 'failed' && (
                <p className="text-xs text-destructive">{t('push.failed')}</p>
            )}

            <div className="flex flex-col gap-2 sm:flex-row-reverse">
                <Button
                    type="button"
                    className="w-full cursor-pointer sm:flex-1"
                    disabled={state === 'working'}
                    onClick={async () => {
                        setState('working');
                        setState(
                            (await subscribeToTicket(token)) ? 'on' : 'failed',
                        );
                    }}
                >
                    {state === 'working' ? (
                        <Spinner />
                    ) : state === 'failed' ? (
                        <BellOff />
                    ) : (
                        <Bell />
                    )}
                    {state === 'failed' ? t('push.retry') : t('push.enable')}
                </Button>

                <Button
                    type="button"
                    variant="ghost"
                    className="w-full cursor-pointer sm:flex-1"
                    onClick={() => setDismissed(true)}
                >
                    {t('push.not_now')}
                </Button>
            </div>
        </section>
    );
}
