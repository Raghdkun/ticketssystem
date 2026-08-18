import { Head } from '@inertiajs/react';
import {
    CalendarDays,
    CheckCircle2,
    Clock,
    MapPin,
    MessageCircle,
    Users,
} from 'lucide-react';
import { useEffect } from 'react';
import { localised, useLocale } from '@/lib/locale';
import { rememberTicket } from '@/lib/tickets';
import { cn } from '@/lib/utils';
import type {
    PublicEvent,
    PublicPlace,
    PublicTicket,
    TicketStatus,
} from '@/types/public';

type Props = { ticket: PublicTicket; event: PublicEvent; place: PublicPlace };

const statusStyles: Record<TicketStatus, string> = {
    pending:
        'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200',
    paid: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
    cancelled: 'bg-red-100 text-red-900 dark:bg-red-950 dark:text-red-200',
    expired:
        'bg-neutral-200 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300',
};

const statusLabels: Record<TicketStatus, { ar: string; en: string }> = {
    pending: { ar: 'بانتظار الدفع', en: 'Awaiting payment' },
    paid: { ar: 'مدفوعة', en: 'Paid' },
    cancelled: { ar: 'ملغاة', en: 'Cancelled' },
    expired: { ar: 'منتهية', en: 'Expired' },
};

export default function TicketPage({ ticket, event, place }: Props) {
    const { locale } = useLocale();

    const title = localised(locale, event.title_ar, event.title_en);
    const placeName = localised(locale, place.name_ar, place.name_en);

    // Keep a local pointer so closing the tab does not lose the ticket.
    useEffect(() => {
        rememberTicket({
            token: ticket.token,
            title,
            savedAt: new Date().toISOString(),
        });
    }, [ticket.token, title]);

    const whatsappHref = place.whatsapp_number
        ? `https://wa.me/${place.whatsapp_number.replace(/\D/g, '')}?text=${encodeURIComponent(
              `${title} — ${ticket.full_name} (${ticket.token.slice(0, 8)})`,
          )}`
        : null;

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

            <main className="mx-auto w-full max-w-md px-4">
                <article className="overflow-hidden rounded-3xl bg-white shadow-xl dark:bg-neutral-900">
                    {/* Stub: event identity over the extracted palette */}
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
                                    locale === 'ar' ? 'ar-SY' : 'en-GB',
                                    { dateStyle: 'medium' },
                                )}
                            </span>
                            <span className="inline-flex items-center gap-1.5">
                                <Clock className="size-4" />
                                {new Date(event.starts_at).toLocaleTimeString(
                                    locale === 'ar' ? 'ar-SY' : 'en-GB',
                                    { timeStyle: 'short' },
                                )}
                            </span>
                        </div>
                    </header>

                    {/* Perforation between stub and counterfoil */}
                    <div
                        className="relative flex items-center"
                        aria-hidden="true"
                    >
                        <div className="size-6 -translate-x-1/2 rounded-full bg-neutral-100 dark:bg-neutral-950" />
                        <div className="flex-1 border-t-2 border-dashed border-neutral-200 dark:border-neutral-700" />
                        <div className="size-6 translate-x-1/2 rounded-full bg-neutral-100 dark:bg-neutral-950" />
                    </div>

                    <div className="space-y-6 p-6">
                        <div
                            className={cn(
                                'flex items-center justify-center gap-2 rounded-xl py-3 text-sm font-semibold',
                                statusStyles[ticket.status],
                            )}
                        >
                            {ticket.status === 'paid' && (
                                <CheckCircle2 className="size-5" />
                            )}
                            {statusLabels[ticket.status][locale]}
                        </div>

                        <div className="flex justify-center">
                            <img
                                src={ticket.qr}
                                alt={
                                    locale === 'ar'
                                        ? 'رمز التذكرة'
                                        : 'Ticket QR code'
                                }
                                className={cn(
                                    'size-56 rounded-xl',
                                    ticket.status !== 'pending' &&
                                        ticket.status !== 'paid' &&
                                        'opacity-40 grayscale',
                                )}
                                width={224}
                                height={224}
                            />
                        </div>

                        <dl className="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <dt className="text-muted-foreground">
                                    {locale === 'ar' ? 'الاسم' : 'Name'}
                                </dt>
                                <dd className="font-medium">
                                    {ticket.full_name}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    {locale === 'ar' ? 'عدد الأشخاص' : 'People'}
                                </dt>
                                <dd className="inline-flex items-center gap-1.5 font-medium">
                                    <Users className="size-4" />
                                    {ticket.quantity}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    {locale === 'ar' ? 'المبلغ' : 'Amount'}
                                </dt>
                                <dd className="font-medium">
                                    {event.is_free
                                        ? locale === 'ar'
                                            ? 'مجاني'
                                            : 'Free'
                                        : `${(event.price * ticket.quantity).toLocaleString()} ${event.currency}`}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    {locale === 'ar'
                                        ? 'رقم التذكرة'
                                        : 'Reference'}
                                </dt>
                                <dd
                                    className="font-mono text-xs font-medium"
                                    dir="ltr"
                                >
                                    {ticket.token.slice(0, 8).toUpperCase()}
                                </dd>
                            </div>
                        </dl>

                        {ticket.status === 'pending' &&
                            ticket.hold_expires_at && (
                                <p className="rounded-lg bg-amber-50 p-3 text-center text-xs text-amber-900 dark:bg-amber-950/50 dark:text-amber-200">
                                    {locale === 'ar'
                                        ? `يرجى الدفع قبل ${new Date(ticket.hold_expires_at).toLocaleString('ar-SY', { dateStyle: 'medium', timeStyle: 'short' })} وإلا سيتم إلغاء الحجز.`
                                        : `Pay before ${new Date(ticket.hold_expires_at).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' })} or this reservation is released.`}
                                </p>
                            )}
                    </div>
                </article>

                {whatsappHref && (
                    <a
                        href={whatsappHref}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="mt-6 flex w-full items-center justify-center gap-2 rounded-xl bg-[#25D366] py-3 font-medium text-white shadow-lg transition hover:brightness-95"
                    >
                        <MessageCircle className="size-5" />
                        {locale === 'ar'
                            ? 'تواصل عبر واتساب'
                            : 'Contact on WhatsApp'}
                    </a>
                )}
            </main>
        </div>
    );
}
