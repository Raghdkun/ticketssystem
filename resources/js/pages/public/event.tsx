import { Head } from '@inertiajs/react';
import { CalendarDays, Check, Clock, Gift, Users } from 'lucide-react';
import { useState } from 'react';
import { BackLink } from '@/components/back-link';
import { BookingForm } from '@/components/booking-form';
import { EventCover } from '@/components/event-cover';
import { FlashToaster } from '@/components/flash-toaster';
import { LanguageToggle } from '@/components/language-toggle';
import { PlaceEdgeTab } from '@/components/place-edge-tab';
import { PromoVideo } from '@/components/promo-video';
import { PublicFooter } from '@/components/public-footer';
import { ShareButton } from '@/components/share-button';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { VenueLink } from '@/components/venue-sheet';
import { localised, useLocale } from '@/lib/locale';
import { useTranslation } from '@/lib/translation';
import type { PublicEvent, PublicPlace, SiblingEvent } from '@/types/public';

type Props = {
    event: PublicEvent;
    place: PublicPlace;
    siblings: SiblingEvent[];
};

export default function EventPage({ event, place, siblings }: Props) {
    const { locale } = useLocale();
    const t = useTranslation();
    const [accepted, setAccepted] = useState<number[]>([]);
    const [quantity, setQuantity] = useState(1);
    const [bookingOpen, setBookingOpen] = useState(false);

    const title = localised(locale, event.title_ar, event.title_en);
    const description = localised(
        locale,
        event.description_ar,
        event.description_en,
    );
    const placeName = localised(locale, place.name_ar, place.name_en);
    const soldOut = event.seats_remaining <= 0;

    const shareUrl =
        typeof window === 'undefined' ? '' : window.location.href.split('?')[0];
    const shareText = t('share.share_text', {
        title,
        place: placeName,
        date: new Date(event.starts_at).toLocaleDateString(
            locale === 'ar' ? 'ar-SY' : 'en-GB',
            { dateStyle: 'medium' },
        ),
    });

    return (
        <div className="min-h-dvh bg-background">
            <Head title={title}>
                <meta
                    name="description"
                    content={`${title} — ${placeName}. ${
                        description ? description.slice(0, 140) : ''
                    }`.trim()}
                />
            </Head>

            <FlashToaster />
            <PlaceEdgeTab place={place} siblings={siblings} />

            <header className="relative">
                <div className="absolute start-4 top-4 z-30">
                    <BackLink
                        href="/"
                        label="common.back_home"
                        className="rounded-lg border border-white/25 bg-black/25 px-3 text-white backdrop-blur hover:bg-black/40 hover:text-white"
                    />
                </div>

                <div className="absolute end-4 top-4 z-30 flex items-center gap-2">
                    <ShareButton
                        url={shareUrl}
                        title={title}
                        text={shareText}
                        compact
                        className="size-9 border-white/25 bg-black/25 text-white backdrop-blur hover:bg-black/40 hover:text-white"
                    />
                    <LanguageToggle />
                </div>

                <div
                    className="relative aspect-[4/5] max-h-[62dvh] w-full overflow-hidden sm:aspect-[16/9] lg:aspect-[3/1]"
                    style={{
                        background: `linear-gradient(140deg, var(--brand-jade-700), var(--brand-jade-900))`,
                    }}
                >
                    {event.promo_video ? (
                        <PromoVideo
                            video={event.promo_video}
                            poster={event.cover?.landscape ?? null}
                            label={title}
                        />
                    ) : (
                        <EventCover cover={event.cover} alt="" priority />
                    )}

                    <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent" />

                    <div className="absolute inset-x-0 bottom-0">
                        <div className="mx-auto w-full max-w-6xl p-5 pe-16 sm:p-8 sm:pe-20">
                            <div className="min-w-0">
                                <div className="mb-3 flex flex-wrap items-center gap-2">
                                    {!soldOut && (
                                        <span className="inline-flex items-center rounded-full bg-brand-cta px-2.5 py-1 text-xs font-semibold text-brand-cta-foreground">
                                            {t('event.seats_only', {
                                                n: event.seats_remaining,
                                            })}
                                        </span>
                                    )}
                                    <span className="inline-flex items-center rounded-full bg-white/15 px-2.5 py-1 text-xs font-medium text-white backdrop-blur">
                                        {t('event.pay_at_venue')}
                                    </span>
                                </div>

                                <p className="text-sm font-medium text-white/80">
                                    <VenueLink
                                        name={placeName}
                                        location={place.location}
                                    />
                                </p>
                                <h1 className="mt-1 text-3xl leading-tight font-bold text-white sm:text-4xl lg:text-5xl">
                                    {title}
                                </h1>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            {/*
             * Below lg this stays a single column, which is what the phone
             * needs. From lg the booking panel moves into its own sticky
             * column: on a 1440px screen the old single column used 47% of
             * the width and pushed the form below the fold.
             */}
            <main
                id="main-content"
                className="mx-auto grid w-full max-w-6xl gap-8 p-5 sm:p-8 lg:grid-cols-[minmax(0,1fr)_23rem] lg:items-start lg:gap-12"
            >
                <div className="min-w-0 space-y-8">
                    <section className="grid grid-cols-2 gap-4 rounded-xl border p-4 text-sm sm:grid-cols-3 lg:gap-6">
                        <div className="space-y-1">
                            <p className="inline-flex items-center gap-1.5 text-muted-foreground">
                                <CalendarDays className="size-4" />
                                {t('event.date')}
                            </p>
                            <p className="font-medium">
                                {new Date(event.starts_at).toLocaleDateString(
                                    locale === 'ar' ? 'ar-SY' : 'en-GB',
                                    { day: 'numeric', month: 'long' },
                                )}
                            </p>
                            {/* The weekday is what people check to decide
                                whether they can actually go. */}
                            <p className="text-xs text-muted-foreground">
                                {new Date(event.starts_at).toLocaleDateString(
                                    locale === 'ar' ? 'ar-SY' : 'en-GB',
                                    { weekday: 'long', year: 'numeric' },
                                )}
                            </p>
                        </div>

                        <div className="space-y-1">
                            <p className="inline-flex items-center gap-1.5 text-muted-foreground">
                                <Clock className="size-4" />
                                {t('event.time')}
                            </p>
                            <p className="font-medium">
                                {new Date(event.starts_at).toLocaleTimeString(
                                    locale === 'ar' ? 'ar-SY' : 'en-GB',
                                    { timeStyle: 'short' },
                                )}
                            </p>
                        </div>

                        <div className="space-y-1">
                            <p className="inline-flex items-center gap-1.5 text-muted-foreground">
                                <Users className="size-4" />
                                {t('event.seats')}
                            </p>
                            <p className="font-medium">
                                {soldOut
                                    ? t('event.sold_out')
                                    : event.seats_remaining}
                            </p>
                        </div>
                    </section>

                    {description && (
                        <p className="leading-relaxed whitespace-pre-line text-muted-foreground">
                            {description}
                        </p>
                    )}

                    {event.perks.length > 0 && (
                        <section className="space-y-3 rounded-xl border p-5">
                            <h2 className="flex items-center gap-2 text-sm font-semibold">
                                <Gift className="size-4 text-primary" />
                                {t('form.perks')}
                            </h2>

                            <ul className="space-y-2">
                                {event.perks.map((perk) => (
                                    <li
                                        key={perk.id}
                                        className="flex items-start gap-2.5 text-sm"
                                    >
                                        <Check className="mt-0.5 size-4 shrink-0 text-primary" />
                                        <span>
                                            {localised(
                                                locale,
                                                perk.body_ar,
                                                perk.body_en,
                                            )}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}

                    {event.gallery.length > 0 && (
                        <section className="-mx-5 overflow-x-auto px-5 sm:mx-0 sm:px-0">
                            <ul className="flex gap-3">
                                {event.gallery.map((item) => (
                                    <li key={item.id} className="shrink-0">
                                        <img
                                            src={`/storage/${item.path}`}
                                            alt=""
                                            loading="lazy"
                                            decoding="async"
                                            className="h-36 w-auto rounded-xl object-cover"
                                        />
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}
                </div>

                {/*
                 * From md the form is part of the page. On a phone it lives
                 * in a sheet opened from the sticky bar: the design's point
                 * is that the form should not sit below three sections of
                 * copy on a device where that means a long scroll.
                 */}
                <aside className="hidden md:block lg:sticky lg:top-8">
                    <BookingForm
                        event={event}
                        place={place}
                        accepted={accepted}
                        onAcceptedChange={setAccepted}
                        quantity={quantity}
                        onQuantityChange={setQuantity}
                    />
                </aside>
            </main>

            {/*
             * Sticky action bar. Phone and tablet only: from lg the booking
             * panel is already sticky in its own column, so repeating the
             * call to action there would just be two of the same button.
             */}
            <div className="sticky bottom-0 z-30 border-t border-border bg-card/95 backdrop-blur md:hidden">
                <div className="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-5 py-3">
                    <div className="min-w-0">
                        <p className="truncate font-display text-xl font-semibold text-primary tabular-nums">
                            {event.is_free
                                ? t('event.free')
                                : `${event.price.toLocaleString()} ${event.currency}`}
                        </p>
                        <p className="truncate text-xs text-muted-foreground">
                            {soldOut
                                ? t('event.sold_out')
                                : t('home.seats_left', {
                                      n: event.seats_remaining,
                                  })}
                        </p>
                    </div>

                    <Sheet open={bookingOpen} onOpenChange={setBookingOpen}>
                        <SheetTrigger asChild>
                            <Button
                                size="lg"
                                disabled={!event.is_open || soldOut}
                                className="shrink-0 cursor-pointer bg-brand-cta text-brand-cta-foreground hover:bg-brand-cta/90"
                            >
                                {soldOut
                                    ? t('event.sold_out')
                                    : t('event.appoint')}
                            </Button>
                        </SheetTrigger>

                        <SheetContent
                            side="bottom"
                            className="max-h-[92dvh] overflow-y-auto rounded-t-[1.75rem]"
                        >
                            <SheetHeader className="text-start">
                                <SheetTitle>
                                    {t('event.reserve_title')}
                                </SheetTitle>
                                <SheetDescription>
                                    {title} · {t('event.reserve_subtitle')}
                                </SheetDescription>
                            </SheetHeader>

                            <div className="px-4 pb-6">
                                <BookingForm
                                    bare
                                    event={event}
                                    place={place}
                                    accepted={accepted}
                                    onAcceptedChange={setAccepted}
                                    quantity={quantity}
                                    onQuantityChange={setQuantity}
                                />
                            </div>
                        </SheetContent>
                    </Sheet>
                </div>
            </div>

            <PublicFooter />
        </div>
    );
}
