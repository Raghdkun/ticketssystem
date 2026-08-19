import { Head } from '@inertiajs/react';
import { CalendarDays, Clock, MapPin, Users } from 'lucide-react';
import { motion, useReducedMotion } from 'motion/react';
import { useEffect } from 'react';
import { LanguageToggle } from '@/components/language-toggle';
import { PlaceEdgeTab } from '@/components/place-edge-tab';
import { PaidStamp } from '@/components/ticket/paid-stamp';
import { StatusBanner } from '@/components/ticket/status-banner';
import { WhatsAppButton } from '@/components/whatsapp-button';
import { useTicketStatus } from '@/hooks/use-ticket-status';
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
    const dateLocale = locale === 'ar' ? 'ar-SY' : 'en-GB';

    useEffect(() => {
        rememberTicket({
            token: ticket.token,
            title,
            savedAt: new Date().toISOString(),
        });
    }, [ticket.token, title]);

    const isSpent = status === 'cancelled' || status === 'expired';

    return (
        <div
            className="min-h-dvh bg-neutral-100 py-6 dark:bg-neutral-950"
            style={
                {
                    '--event-primary': event.theme.primary,
                    '--event-secondary': event.theme.secondary,
                    '--event-on-primary': event.theme.on_primary,
                } as React.CSSProperties
            }
        >
            <Head title={title} />

            <PlaceEdgeTab place={place} siblings={siblings} />

            <main className="mx-auto w-full max-w-md px-4">
                <div className="mb-4 flex justify-end">
                    <LanguageToggle className="bg-black/10 text-foreground dark:bg-white/10" />
                </div>

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
                    className="relative overflow-hidden rounded-3xl bg-white shadow-xl dark:bg-neutral-900"
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
                                'linear-gradient(135deg, var(--event-primary), var(--event-secondary))',
                            color: 'var(--event-on-primary)',
                        }}
                    >
                        <p className="inline-flex items-center gap-1.5 text-sm opacity-80">
                            <MapPin className="size-4" />
                            {placeName}
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
                                        : `${(event.price * ticket.quantity).toLocaleString()} ${event.currency}`}
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
                                    {ticket.token.slice(0, 8).toUpperCase()}
                                </dd>
                            </div>
                        </dl>

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
                            <p className="rounded-lg bg-amber-50 p-3 text-center text-xs text-amber-900 dark:bg-amber-950/50 dark:text-amber-200">
                                {t('ticket.pay_before', {
                                    time: new Date(
                                        ticket.hold_expires_at,
                                    ).toLocaleString(dateLocale, {
                                        dateStyle: 'medium',
                                        timeStyle: 'short',
                                    }),
                                })}
                            </p>
                        )}
                    </div>
                </motion.article>

                <WhatsAppButton
                    number={place.whatsapp_number}
                    message={`${title} — ${ticket.full_name} (${ticket.token.slice(0, 8).toUpperCase()})`}
                    label={t('common.whatsapp')}
                    className="mt-6 w-full"
                />
            </main>
        </div>
    );
}
