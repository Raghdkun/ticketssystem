import { Head } from '@inertiajs/react';
import { CalendarDays, Check, Clock, Gift, Users } from 'lucide-react';
import { motion, useReducedMotion } from 'motion/react';
import { useEffect } from 'react';
import { BackLink } from '@/components/back-link';
import { FlashToaster } from '@/components/flash-toaster';
import { InstallPrompt } from '@/components/install-prompt';
import { LanguageToggle } from '@/components/language-toggle';
import { PlaceEdgeTab } from '@/components/place-edge-tab';
import { PublicFooter } from '@/components/public-footer';
import { PushOptIn } from '@/components/push-opt-in';
import { ShareButton } from '@/components/share-button';
import { HoldCountdown } from '@/components/ticket/hold-countdown';
import { PaidStamp } from '@/components/ticket/paid-stamp';
import { ReleaseSeats } from '@/components/ticket/release-seats';
import { StatusBanner } from '@/components/ticket/status-banner';
import { VenueLink } from '@/components/venue-sheet';
import { WhatsAppButton } from '@/components/whatsapp-button';
import { useTicketStatus } from '@/hooks/use-ticket-status';
import { dateTag } from '@/lib/format';
import { localised, useLocale } from '@/lib/locale';
import { rememberTicket } from '@/lib/tickets';
import { useTranslation } from '@/lib/translation';
import { cn } from '@/lib/utils';
import type {
    PublicEvent,
    PublicPlace,
    PublicTicket,
    SiblingEvent,
} from '@/types/public';

type Props = {
    ticket: PublicTicket;
    event: PublicEvent;
    place: PublicPlace;
    siblings: SiblingEvent[];
};

export default function TicketPage({ ticket, event, place, siblings }: Props) {
    const { locale } = useLocale();
    const t = useTranslation();
    const reduceMotion = useReducedMotion();

    const { status, verifiedAt, justChanged } = useTicketStatus(
        ticket.token,
        ticket.status,
        ticket.verified_at,
    );

    const title = localised(locale, event.title_ar, event.title_en);
    const placeName = localised(locale, place.name_ar, place.name_en);
    const dateLocale = dateTag(locale);

    useEffect(() => {
        rememberTicket({
            token: ticket.token,
            title,
            savedAt: new Date().toISOString(),
        });
    }, [ticket.token, title]);

    const eventUrl =
        typeof window === 'undefined'
            ? ''
            : `${window.location.origin}/${place.slug}/${event.slug}`;
    const shareText = t('share.share_text', {
        title,
        place: placeName,
        date: new Date(event.starts_at).toLocaleDateString(dateLocale, {
            dateStyle: 'medium',
        }),
    });

    const isSpent =
        status === 'cancelled' || status === 'expired' || status === 'no_show';

    return (
        <div className="min-h-dvh bg-neutral-100 py-6 dark:bg-neutral-950">
            <Head title={title}>
                <meta name="description" content={`${title} — ${placeName}`} />
                <meta name="robots" content="noindex" />
            </Head>

            <FlashToaster />
            <PlaceEdgeTab place={place} siblings={siblings} />
            <InstallPrompt />

            <div className="mx-auto flex w-full max-w-md items-center justify-between gap-3 px-4 pb-4 lg:max-w-4xl">
                {/* A ticket is usually opened from a saved link, so the way
                    back is to the event it is for, not browser history. */}
                <BackLink
                    href={`/${place.slug}/${event.slug}`}
                    label="common.back_to_event"
                />
                <div className="flex items-center gap-1">
                    <LanguageToggle className="bg-black/10 text-foreground dark:bg-white/10" />

                    {/* Only a live hold has seats to give back, and the
                        control is tucked into an overflow menu: the loudest
                        thing on a ticket must never be what destroys it. */}
                    {status === 'pending' && (
                        <ReleaseSeats token={ticket.token} />
                    )}
                </div>
            </div>

            <main
                id="main-content"
                className="mx-auto grid w-full max-w-md gap-6 px-4 lg:max-w-4xl lg:grid-cols-[26rem_minmax(0,1fr)] lg:items-start lg:gap-10"
            >
                {/* The card is fully visible by default and only rises into
                    place. Animating opacity from 0 would hide the ticket
                    entirely if motion never runs (throttled or backgrounded
                    tab), which is not an acceptable failure mode here. */}
                <motion.article
                    initial={reduceMotion ? false : { y: 18 }}
                    animate={{ y: 0 }}
                    transition={{
                        duration: reduceMotion ? 0 : 0.45,
                        ease: [0.22, 1, 0.36, 1],
                    }}
                    className="shadow-brand grain relative overflow-hidden rounded-3xl bg-white dark:bg-neutral-900"
                >
                    <PaidStamp
                        show={status === 'paid'}
                        animate={justChanged}
                        label={t('ticket.stamp')}
                    />

                    <header
                        className="relative p-6"
                        style={{
                            background:
                                'linear-gradient(140deg, var(--brand-jade-700), var(--brand-jade-900))',
                            color: 'var(--brand-paper-100)',
                        }}
                    >
                        {/* The ticket is what someone opens on the way to the
                            door, so the venue has to be reachable from here. */}
                        <p className="text-sm opacity-80">
                            <VenueLink
                                name={placeName}
                                location={event.location}
                            />
                        </p>
                        <h1 className="mt-1 text-2xl leading-tight font-bold">
                            {title}
                        </h1>

                        <div className="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-sm opacity-90">
                            <span className="inline-flex items-center gap-1.5">
                                <CalendarDays className="size-4" />
                                {new Date(event.starts_at).toLocaleDateString(
                                    dateLocale,
                                    {
                                        dateStyle: 'medium',
                                    },
                                )}
                            </span>
                            <span className="inline-flex items-center gap-1.5">
                                <Clock className="size-4" />
                                {new Date(event.starts_at).toLocaleTimeString(
                                    dateLocale,
                                    {
                                        timeStyle: 'short',
                                    },
                                )}
                            </span>
                        </div>
                    </header>

                    <div
                        className="relative flex items-center"
                        aria-hidden="true"
                    >
                        <div className="size-6 -translate-x-1/2 rounded-full bg-neutral-100 dark:bg-neutral-950" />
                        <div className="flex-1 border-t-2 border-dashed border-neutral-200 dark:border-neutral-700" />
                        <div className="size-6 translate-x-1/2 rounded-full bg-neutral-100 dark:bg-neutral-950" />
                    </div>

                    <div className="space-y-6 p-6">
                        <StatusBanner
                            status={status}
                            label={t(`ticket.status.${status}`)}
                            pulse={justChanged}
                        />

                        <div className="flex justify-center">
                            <img
                                src={ticket.qr}
                                alt={t('ticket.qr_alt')}
                                className={cn(
                                    'size-56 rounded-xl transition duration-500',
                                    isSpent && 'opacity-40 grayscale',
                                )}
                                width={224}
                                height={224}
                            />
                        </div>

                        <dl className="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('ticket.name')}
                                </dt>
                                <dd className="font-medium">
                                    {ticket.full_name}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('ticket.people')}
                                </dt>
                                <dd className="inline-flex items-center gap-1.5 font-medium">
                                    <Users className="size-4" />
                                    {ticket.quantity}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('ticket.amount')}
                                </dt>
                                <dd className="font-medium">
                                    {event.is_free
                                        ? t('event.free')
                                        : `${(event.price * ticket.quantity).toLocaleString('en-GB')} ${event.currency}`}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('ticket.reference')}
                                </dt>
                                <dd
                                    className="font-mono text-xs font-medium"
                                    dir="ltr"
                                >
                                    {`${ticket.token.slice(0, 4)} ${ticket.token.slice(4, 8)}`.toUpperCase()}
                                </dd>
                            </div>
                        </dl>

                        {event.perks.length > 0 && (
                            <div className="space-y-2 rounded-lg bg-muted/60 p-3">
                                <p className="flex items-center gap-1.5 text-xs font-semibold">
                                    <Gift className="size-3.5" />
                                    {t('form.perks')}
                                </p>
                                <ul className="space-y-1">
                                    {event.perks.map((perk) => (
                                        <li
                                            key={perk.id}
                                            className="flex items-start gap-2 text-xs"
                                        >
                                            <Check className="mt-0.5 size-3.5 shrink-0" />
                                            {localised(
                                                locale,
                                                perk.body_ar,
                                                perk.body_en,
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        {status === 'paid' && verifiedAt && (
                            <p className="text-center text-xs text-muted-foreground">
                                {t('ticket.verified_at', {
                                    time: new Date(verifiedAt).toLocaleString(
                                        dateLocale,
                                        {
                                            dateStyle: 'medium',
                                            timeStyle: 'short',
                                        },
                                    ),
                                })}
                            </p>
                        )}

                        {status === 'pending' && ticket.hold_expires_at && (
                            <HoldCountdown
                                expiresAt={ticket.hold_expires_at}
                                amount={
                                    event.is_free
                                        ? t('event.free')
                                        : `${(event.price * ticket.quantity).toLocaleString('en-GB')} ${event.currency}`
                                }
                            />
                        )}
                    </div>
                </motion.article>

                <div className="space-y-4 lg:pt-2">
                    {status === 'pending' && <PushOptIn token={ticket.token} />}

                    <WhatsAppButton
                        number={place.whatsapp_number}
                        message={`${title} — ${ticket.full_name} (${`${ticket.token.slice(0, 4)} ${ticket.token.slice(4, 8)}`.toUpperCase()})`}
                        label={t('common.whatsapp')}
                        className="w-full"
                    />

                    {/*
                     * Shares the event, never the ticket. The ticket URL is a
                     * bearer token: anything holding it can view the booking,
                     * so it must not be one tap from a public timeline.
                     */}
                    <ShareButton
                        url={eventUrl}
                        title={title}
                        text={shareText}
                        className="w-full"
                    />

                    <p className="text-center text-xs text-muted-foreground">
                        {t('share.ticket_note')}
                    </p>
                </div>
            </main>

            <PublicFooter />
        </div>
    );
}
