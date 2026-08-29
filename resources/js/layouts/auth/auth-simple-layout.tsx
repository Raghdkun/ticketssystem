import { Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { LanguageToggle } from '@/components/language-toggle';
import { useTranslation } from '@/lib/translation';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const t = useTranslation();
    const { platform } = usePage<{ platform: { name: string } }>().props;

    return (
        <div className="flex min-h-svh flex-col bg-background">
            {/* A brand band anchors the page before any content loads. It
                carries jade, not basalt: basalt on the dark theme's basalt
                background is invisible. */}
            <div
                className="h-1.5 w-full shrink-0"
                style={{ backgroundColor: 'var(--brand-jade-700)' }}
            />

            {/* app.blade.php emits the skip link on every page, so every
                layout has to provide its target. Without it the link was a
                dead jump on the whole auth flow. */}
            <main
                id="main-content"
                className="flex flex-1 flex-col items-center justify-center gap-6 p-6 md:p-10"
            >
                <div className="w-full max-w-sm">
                    <div className="flex flex-col gap-8">
                        <div className="flex items-center justify-between gap-4">
                            <Link
                                href={home()}
                                className="flex items-center gap-2 rounded-md font-medium coarse:min-h-11 coarse:min-w-11"
                            >
                                <AppLogoIcon className="mark-animated size-9 text-primary" />
                                <span className="sr-only">{title}</span>
                            </Link>

                            <LanguageToggle className="border bg-transparent text-foreground hover:bg-muted" />
                        </div>

                        <div className="space-y-2">
                            <h1 className="text-2xl font-bold">{title}</h1>
                            <p className="text-sm text-muted-foreground">
                                {description}
                            </p>
                        </div>

                        {children}

                        {/* Registration is closed by design, so the footer
                            carries the legal links a sign-up flow would. */}
                        <p className="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
                            <span>{platform?.name}</span>
                            <span aria-hidden>·</span>
                            <Link
                                href="/privacy"
                                className="inline-flex items-center rounded-sm underline-offset-4 hover:text-foreground hover:underline coarse:min-h-11"
                            >
                                {t('legal.privacy')}
                            </Link>
                            <span aria-hidden>·</span>
                            <Link
                                href="/terms"
                                className="inline-flex items-center rounded-sm underline-offset-4 hover:text-foreground hover:underline coarse:min-h-11"
                            >
                                {t('legal.terms')}
                            </Link>
                        </p>
                    </div>
                </div>
            </main>
        </div>
    );
}
