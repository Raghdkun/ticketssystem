import { Head, Link } from '@inertiajs/react';
import { CalendarDays, MapPin, Ticket as TicketIcon } from 'lucide-react';
import { BackLink } from '@/components/back-link';
import { EmptyState } from '@/components/empty-state';
import { ImageSlider } from '@/components/image-slider';
import { LanguageToggle } from '@/components/language-toggle';
import { PublicFooter } from '@/components/public-footer';
import { VenueLink } from '@/components/venue-sheet';
import type { VenueLocation } from '@/components/venue-sheet';
import { dateTag } from '@/lib/format';
import { localised, useLocale } from '@/lib/locale';
import { useTranslation } from '@/lib/translation';

type EventCard = {
    slug: string;
    title_ar: string;
    title_en: string;
    starts_at: string;
    cover: string | null;
    price: number;
    currency: string;
    seats_remaining: number;
    is_open: boolean;
    location: string | null;
};

type Props = {
    place: {
        slug: string;
        name_ar: string;
        name_en: string;
        logo: string | null;
        whatsapp_number: string | null;
    };
    locations: VenueLocation[];
    upcoming: EventCard[];
    past: EventCard[];
};

export default function PlacePage({ place, locations, upcoming, past }: Props) {
    const t = useTranslation();
    const { locale } = useLocale();
    const dateLocale = dateTag(locale);
    const name = localised(locale, place.name_ar, place.name_en);

    const card = (event: EventCard, dim = false) => (
        <li key={event.slug}>
            <Link
                href={`/${place.slug}/${event.slug}`}
                className={`brand-surface group flex h-full flex-col overflow-hidden rounded-xl border transition-all duration-200 hover:border-primary/40 hover:shadow-md ${dim ? 'opacity-70' : ''}`}
            >
                <div className="relative grid aspect-video place-items-center bg-muted">
                    {event.cover ? (
                        <img
                            src={`/storage/${event.cover}`}
                            alt=""
                            loading="lazy"
                            className="absolute inset-0 size-full object-cover"
                        />
                    ) : (
                        <TicketIcon
                            className="size-7 text-muted-foreground/60"
                            aria-hidden
                        />
                    )}
                </div>

                <div className="flex flex-1 flex-col gap-1.5 p-4">
                    <p className="leading-tight font-semibold">
                        {localised(locale, event.title_ar, event.title_en)}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {new Date(event.starts_at).toLocaleDateString(
                            dateLocale,
                            {
                                day: 'numeric',
                                month: 'long',
                            },
                        )}
                        {event.location ? ` · ${event.location}` : ''}
                    </p>
                    <p className="mt-auto pt-2 text-sm font-medium">
                        {event.price > 0
                            ? `${event.price.toLocaleString('en-US')} ${event.currency}`
                            : t('event.free')}
                        {!dim && event.seats_remaining <= 0 && (
                            <span className="ms-2 text-xs text-muted-foreground">
                                {t('home.sold_out')}
                            </span>
                        )}
                    </p>
                </div>
            </Link>
        </li>
    );

    return (
        <div className="min-h-dvh bg-background">
            <Head title={name} />

            <div className="mx-auto flex w-full max-w-5xl items-center justify-between gap-3 p-4">
                <BackLink href="/" label="common.back_home" />
                <LanguageToggle className="min-h-9 border bg-transparent py-1 text-foreground hover:bg-muted" />
            </div>

            <main
                id="main-content"
                className="mx-auto w-full max-w-5xl space-y-10 px-4 pb-12"
            >
                <header className="flex flex-wrap items-center gap-4">
                    {place.logo && (
                        <img
                            src={`/storage/${place.logo}`}
                            alt=""
                            className="size-16 shrink-0 rounded-xl object-cover"
                        />
                    )}
                    <div className="min-w-0">
                        <h1 className="text-3xl font-bold sm:text-4xl">
                            {name}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {upcoming.length > 0
                                ? t('place_page.upcoming_count', {
                                      n: upcoming.length,
                                  })
                                : t('place_page.nothing_scheduled')}
                        </p>
                    </div>
                </header>

                {locations.length > 0 && (
                    <section className="space-y-3">
                        <h2 className="flex items-center gap-2 text-lg font-semibold">
                            <MapPin className="size-5" aria-hidden />
                            {t('owner.locations')}
                        </h2>

                        <ul className="grid gap-4 sm:grid-cols-2">
                            {locations.map((location) => (
                                <li
                                    key={location.name}
                                    className="brand-surface flex flex-col gap-3 rounded-xl border p-4"
                                >
                                    {location.images.length > 0 && (
                                        <ImageSlider
                                            images={location.images}
                                            alt={location.name}
                                            className="overflow-hidden rounded-lg"
                                        />
                                    )}
                                    <div>
                                        <p className="font-medium">
                                            {/* Opens the same sheet as an event
                                                page: map, landmark, directions. */}
                                            <VenueLink
                                                name={location.name}
                                                location={location}
                                            />
                                        </p>
                                        <p className="mt-0.5 text-sm text-muted-foreground">
                                            {localised(
                                                locale,
                                                location.address_ar ?? '',
                                                location.address_en ?? '',
                                            ) || t('place.no_address')}
                                        </p>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                <section className="space-y-3">
                    <h2 className="flex items-center gap-2 text-lg font-semibold">
                        <CalendarDays className="size-5" aria-hidden />
                        {t('home.whats_on')}
                    </h2>

                    {upcoming.length === 0 ? (
                        <EmptyState
                            icon={CalendarDays}
                            title={t('place_page.nothing_scheduled')}
                        />
                    ) : (
                        <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {upcoming.map((event) => card(event))}
                        </ul>
                    )}
                </section>

                {past.length > 0 && (
                    <section className="space-y-3">
                        <h2 className="text-lg font-semibold">
                            {t('place_page.past')}
                        </h2>
                        <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {past.map((event) => card(event, true))}
                        </ul>
                    </section>
                )}
            </main>

            <PublicFooter />
        </div>
    );
}
