import { Head, Link } from '@inertiajs/react';
import { useReducedMotion } from 'motion/react';
import { BackLink } from '@/components/back-link';
import Magnet from '@/components/bits/magnet';
import { Wordmark } from '@/components/brand/wordmark';
import { FlashToaster } from '@/components/flash-toaster';
import { LanguageToggle } from '@/components/language-toggle';
import { BlurHeading } from '@/components/motion/blur-heading';
import { PublicFooter } from '@/components/public-footer';
import { ThemeToggle } from '@/components/theme-toggle';
import { WhatsAppButton } from '@/components/whatsapp-button';
import { useFinePointer } from '@/hooks/use-fine-pointer';
import { useTranslation } from '@/lib/translation';
import { login } from '@/routes';

type Props = {
    /** The pitch, in the page's language, from platform settings. */
    pitch: string | null;
    /** The platform's support number, from platform settings. */
    whatsapp: string | null;
};

/**
 * For a venue that is not a partner yet.
 *
 * Registration is closed by design, so the page offers a conversation
 * rather than a form: one WhatsApp button to whoever mints invitations,
 * and a quiet way in for a venue that is already a partner.
 */
export default function ForVenues({ pitch, whatsapp }: Props) {
    const t = useTranslation();
    const finePointer = useFinePointer();
    const reduceMotion = useReducedMotion();

    return (
        <div className="flex min-h-dvh flex-col bg-background">
            <Head title={t('venues.title')}>
                <meta
                    name="description"
                    content={pitch?.split('\n')[0] ?? t('venues.heading')}
                />
            </Head>

            <FlashToaster />

            <main
                id="main-content"
                className="mx-auto w-full max-w-2xl flex-1 p-6"
            >
                <div className="mb-8 flex items-center justify-between gap-4">
                    <BackLink href="/" />
                    <div className="flex items-center gap-2">
                        <ThemeToggle className="border bg-transparent text-foreground hover:bg-muted" />
                        <LanguageToggle className="border bg-transparent text-foreground hover:bg-muted" />
                    </div>
                </div>

                <Wordmark decorative className="h-12 text-foreground" />

                <p className="mt-8 text-xs font-extrabold tracking-wide text-primary-text uppercase">
                    {t('venues.eyebrow')}
                </p>
                <h1 className="mt-2 text-3xl font-extrabold text-balance">
                    <BlurHeading
                        text={t('venues.heading')}
                        className="gap-x-[0.3em]"
                    />
                </h1>
                <div className="mt-2 h-1 w-12 bg-primary" aria-hidden="true" />

                {pitch && (
                    <p className="mt-6 max-w-[65ch] text-lg leading-relaxed whitespace-pre-line text-muted-foreground">
                        {pitch}
                    </p>
                )}

                <div className="mt-8 space-y-4">
                    {whatsapp ? (
                        <Magnet
                            disabled={!finePointer || reduceMotion === true}
                            padding={60}
                            magnetStrength={5}
                            style={{ display: 'block' }}
                            innerClassName="block"
                        >
                            <WhatsAppButton
                                number={whatsapp}
                                message={t('venues.whatsapp_message')}
                                label={t('venues.whatsapp')}
                                className="min-h-13 text-base font-extrabold"
                            />
                        </Magnet>
                    ) : (
                        <p
                            role="status"
                            className="rounded-xl bg-status-draft-bg p-4 text-center text-sm font-extrabold text-status-draft-fg"
                        >
                            {t('venues.no_number')}
                        </p>
                    )}

                    <p className="text-center text-sm text-muted-foreground">
                        {t('venues.partner')}{' '}
                        <Link
                            href={login()}
                            className="font-medium text-primary-text underline-offset-4 hover:underline"
                        >
                            {t('venues.sign_in')}
                        </Link>
                    </p>
                </div>
            </main>

            <PublicFooter />
        </div>
    );
}
