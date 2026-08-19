import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { FlashToaster } from '@/components/flash-toaster';
import { PublicFooter } from '@/components/public-footer';
import { useLocale } from '@/lib/locale';
import { useTranslation } from '@/lib/translation';

type Section = { heading: string; body: string };
type Props = {
    document: 'privacy' | 'terms';
    legal: Record<string, Section[]>;
};

export default function Legal({ document: doc }: Props) {
    const { legal } = usePage<Props>().props;
    const { direction } = useLocale();
    const t = useTranslation();

    const sections = legal?.[doc] ?? [];
    const title = t(
        doc === 'privacy' ? 'legal.privacy_title' : 'legal.terms_title',
    );

    return (
        <div className="flex min-h-dvh flex-col bg-background">
            <Head title={title}>
                <meta name="description" content={sections[0]?.body ?? title} />
            </Head>

            <FlashToaster />

            <main
                id="main-content"
                className="mx-auto w-full max-w-2xl flex-1 p-6"
            >
                <Link
                    href="/"
                    className="mb-8 inline-flex cursor-pointer items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
                >
                    <ArrowLeft
                        className={direction === 'rtl' ? 'rotate-180' : ''}
                    />
                    {t('common.back')}
                </Link>

                <h1 className="text-3xl font-bold tracking-tight">{title}</h1>

                {/* Roughly 65 characters per line keeps long prose readable. */}
                <div className="mt-8 space-y-8">
                    {sections.map((section) => (
                        <section key={section.heading} className="space-y-2">
                            <h2 className="text-lg font-semibold">
                                {section.heading}
                            </h2>
                            <p className="max-w-[65ch] leading-relaxed text-muted-foreground">
                                {section.body}
                            </p>
                        </section>
                    ))}
                </div>
            </main>

            <PublicFooter />
        </div>
    );
}
