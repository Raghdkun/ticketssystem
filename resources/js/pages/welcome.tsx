import { Head, Link, usePage } from '@inertiajs/react';
import { CalendarDays, MapPin, Search } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { EmptyState } from '@/components/empty-state';
import { FlashToaster } from '@/components/flash-toaster';
import { LanguageToggle } from '@/components/language-toggle';
import { PublicFooter } from '@/components/public-footer';
import { Button } from '@/components/ui/button';
import { localised, useLocale } from '@/lib/locale';
import { useTranslation } from '@/lib/translation';

type HomeEvent = {
    slug: string;
    title_ar: string;
    title_en: string;
    starts_at: string;
    cover: string | null;
    is_free: boolean;
    price: number;
    currency: string;
    seats_remaining: number;
    place_slug: string;
    place_name_ar: string;
    place_name_en: string;
};

export default function Welcome({ events }: { events: HomeEvent[] }) {
    const { locale } = useLocale();
    const { platform } = usePage<{
        platform: { name: string; tagline: string | null };
    }>().props;
    const t = useTranslation();
    const dateLocale = locale === 'ar' ? 'ar-SY' : 'en-GB';

    return (
        <div className="min-h-dvh bg-background">
            <Head title={t('home.whats_on')}>
                <meta name="description" content={t('home.tagline')} />
            </Head>

            <FlashToaster />

            <header className="border-b">
                <div className="mx-auto flex w-full max-w-5xl items-center justify-between gap-4 p-5">
                    <div className="flex items-center gap-2">
                        <AppLogoIcon className="size-7 text-primary" />
                        <span className="font-bold">{platform.name}</span>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button
                            asChild
                            size="sm"
                            className="bg-brand-cta text-brand-cta-foreground hover:bg-brand-cta/90"
                        >
                            {/* The label is hidden on narrow screens, so the
                                link carries its own accessible name. */}
                            <Link
                                href="/my-tickets"
                                aria-label={t('home.find_ticket')}
                            >
                                <Search />
                                <span className="hidden sm:inline">
                                    {t('home.find_ticket')}
                                </span>
                            </Link>
                        </Button>
                        <LanguageToggle className="bg-black/10 text-foreground dark:bg-white/10" />
                    </div>
                </div>
            </header>

            <main
                id="main-content"
                className="mx-auto w-full max-w-5xl space-y-10 p-5"
            >
                <section className="brand-surface-strong grain relative overflow-hidden rounded-3xl border p-8 sm:p-12">
                    <h1 className="max-w-2xl text-3xl leading-tight font-bold tracking-tight sm:text-5xl">
                        {platform.name}
                    </h1>
                    <p className="mt-3 max-w-xl text-base text-muted-foreground sm:text-lg">
                        {platform.tagline ?? t('home.tagline')}
                    </p>

                    <Button
                        asChild
                        size="lg"
                        className="mt-6 cursor-pointer bg-brand-cta text-brand-cta-foreground hover:bg-brand-cta/90"
                    >
                        <a href="#whats-on">{t('home.whats_on')}</a>
                    </Button>
                </section>

                <section id="whats-on" className="space-y-4">
                    <h2 className="text-2xl font-bold">{t('home.whats_on')}</h2>

                    {events.length === 0 ? (
                        <EmptyState
                            icon={CalendarDays}
                            title={t('home.none')}
                        />
                    ) : (
                        <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {events.map((event) => {
                                const soldOut = event.seats_remaining <= 0;

                                return (
                                    <li
                                        key={`${event.place_slug}/${event.slug}`}
                                    >
                                        <Link
                                            href={`/${event.place_slug}/${event.slug}`}
                                            className="group block overflow-hidden rounded-xl border transition hover:shadow-md"
                                        >
                                            <div
                                                className="relative aspect-video"
                                                style={{
                                                    backgroundColor:
                                                        'var(--brand-jade-700)',
                                                }}
                                            >
                                                {event.cover && (
                                                    <img
                                                        src={`/storage/${event.cover}`}
                                                        alt=""
                                                        className="size-full object-cover"
                                                        loading="lazy"
                                                        decoding="async"
                                                    />
                                                )}
                                            </div>

                                            <div className="space-y-2 p-4">
                                                <h2 className="line-clamp-2 leading-tight font-medium">
                                                    {localised(
                                                        locale,
                                                        event.title_ar,
                                                        event.title_en,
                                                    )}
                                                </h2>

                                                <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                                    <MapPin className="size-3.5 shrink-0" />
                                                    <span className="truncate">
                                                        {localised(
                                                            locale,
                                                            event.place_name_ar,
                                                            event.place_name_en,
                                                        )}
                                                    </span>
                                                </p>

                                                <div className="flex flex-wrap items-center justify-between gap-2 pt-1">
                                                    <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                                        <CalendarDays className="size-3.5" />
                                                        {new Date(
                                                            event.starts_at,
                                                        ).toLocaleDateString(
                                                            dateLocale,
                                                            {
                                                                dateStyle:
                                                                    'medium',
                                                            },
                                                        )}
                                                    </span>

                                                    <span className="text-sm font-semibold">
                                                        {event.is_free
                                                            ? t('event.free')
                                                            : `${event.price.toLocaleString()} ${event.currency}`}
                                                    </span>
                                                </div>

                                                <p className="text-xs text-muted-foreground">
                                                    {soldOut
                                                        ? t('home.sold_out')
                                                        : t('home.seats_left', {
                                                              n: event.seats_remaining,
                                                          })}
                                                </p>
                                            </div>
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </section>
            </main>

            <PublicFooter />
        </div>
    );
}
