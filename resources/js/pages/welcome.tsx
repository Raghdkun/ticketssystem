import { Head, Link } from '@inertiajs/react';
import {
    CalendarDays,
    MapPin,
    Search,
    Ticket as TicketIcon,
} from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { FlashToaster } from '@/components/flash-toaster';
import { LanguageToggle } from '@/components/language-toggle';
import { Button } from '@/components/ui/button';
import { localised, useLocale } from '@/lib/locale';
import { useTranslation } from '@/lib/translation';

type HomeEvent = {
    slug: string;
    title_ar: string;
    title_en: string;
    starts_at: string;
    cover: string | null;
    primary_color: string;
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
                        <TicketIcon className="size-5" />
                        <span className="font-bold">Tickets</span>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button
                            asChild
                            size="sm"
                            className="bg-brand-cta text-brand-cta-foreground hover:bg-brand-cta/90"
                        >
                            <Link href="/my-tickets">
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

            <main className="mx-auto w-full max-w-5xl space-y-8 p-5">
                <p className="text-lg text-muted-foreground">
                    {t('home.tagline')}
                </p>

                <section className="space-y-4">
                    <h1 className="text-2xl font-bold">{t('home.whats_on')}</h1>

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
                                                        event.primary_color,
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
        </div>
    );
}
