import { Share, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { isIos, isStandalone } from '@/lib/pwa';
import { useTranslation } from '@/lib/translation';

type InstallEvent = Event & { prompt: () => Promise<void> };

const DISMISSED_KEY = 'install-prompt-dismissed';

function initiallyHidden(): boolean {
    if (typeof window === 'undefined') {
        return true;
    }

    try {
        return isStandalone() || localStorage.getItem(DISMISSED_KEY) === '1';
    } catch {
        return isStandalone();
    }
}

/**
 * Friendly install invitation.
 *
 * Android/Chrome fire `beforeinstallprompt`, which we defer and trigger from
 * our own button. iOS Safari fires nothing and offers no programmatic install,
 * so there we can only show the manual Add to Home Screen instruction — which
 * is also the only way web push works on iOS at all.
 */
export function InstallPrompt() {
    const t = useTranslation();
    const [deferred, setDeferred] = useState<InstallEvent | null>(null);

    // Derived at first render rather than inside an effect: both inputs are
    // stable for the life of the page, so an effect would only add a pass.
    const [hidden, setHidden] = useState(initiallyHidden);
    const [iosDevice] = useState(
        () => typeof window !== 'undefined' && isIos(),
    );

    useEffect(() => {
        const onPrompt = (event: Event) => {
            event.preventDefault();
            setDeferred(event as InstallEvent);
        };

        window.addEventListener('beforeinstallprompt', onPrompt);

        return () =>
            window.removeEventListener('beforeinstallprompt', onPrompt);
    }, []);

    const dismiss = () => {
        try {
            localStorage.setItem(DISMISSED_KEY, '1');
        } catch {
            // Private mode: hiding it for this page view is good enough.
        }

        setDeferred(null);
        setHidden(true);
    };

    const showIosHint = iosDevice;

    if (hidden || (!deferred && !showIosHint)) {
        return null;
    }

    return (
        <div className="fixed inset-x-0 bottom-0 z-50 border-t bg-background/95 p-4 backdrop-blur">
            <div className="mx-auto flex max-w-md items-start gap-3">
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-medium">{t('pwa.title')}</p>
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        {showIosHint ? (
                            <span className="inline-flex items-center gap-1">
                                {t('pwa.ios_hint')}
                                <Share className="size-3.5" />
                            </span>
                        ) : (
                            t('pwa.body')
                        )}
                    </p>
                </div>

                {deferred && (
                    <Button
                        size="sm"
                        onClick={() => {
                            void deferred.prompt();
                            dismiss();
                        }}
                    >
                        {t('pwa.install')}
                    </Button>
                )}

                <Button
                    size="icon"
                    variant="ghost"
                    aria-label={t('common.cancel')}
                    onClick={dismiss}
                >
                    <X />
                </Button>
            </div>
        </div>
    );
}
