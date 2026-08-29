import { Head } from '@inertiajs/react';
import { Download, MessageCircle } from 'lucide-react';
import EventController from '@/actions/App/Http/Controllers/Owner/EventController';
import Heading from '@/components/heading';
import { Counter } from '@/components/motion/counter';
import { Stagger, StaggerItem } from '@/components/motion/stagger';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { localised, useLocale } from '@/lib/locale';
import { useTranslation } from '@/lib/translation';
import type { TicketStatus } from '@/types/public';

type Report = {
    by_status: Record<string, { bookings: number; seats: number }>;
    totals: {
        bookings: number;
        seats_capacity: number;
        seats_booked: number;
        seats_paid: number;
        seats_arrived: number;
        seats_remaining: number;
    };
    money: {
        currency: string;
        price: number;
        collected: number;
        outstanding: number;
        potential: number;
    };
    rates: { attendance: number; fill: number; no_show_bookings: number };
};

type Props = {
    event: {
        id: number;
        title_ar: string;
        title_en: string;
        starts_at: string;
        is_free: boolean;
    };
    report: Report;
    waiting: {
        id: number;
        full_name: string;
        phone: string;
        notified_at: string | null;
    }[];
};

function Metric({
    label,
    value,
    suffix,
    tone,
}: {
    label: string;
    value: number;
    suffix?: string;
    tone?: string;
}) {
    return (
        <StaggerItem className="brand-surface rounded-xl border p-4 transition-colors hover:border-primary/40">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className={`mt-1 text-2xl font-bold tabular-nums ${tone ?? ''}`}>
                <Counter value={value} />
                {suffix && (
                    <span className="ms-1 text-sm font-medium">{suffix}</span>
                )}
            </p>
        </StaggerItem>
    );
}

export default function EventReportPage({ event, report, waiting }: Props) {
    const { locale } = useLocale();
    const t = useTranslation();

    const title = localised(locale, event.title_ar, event.title_en);
    const money = report.money;

    return (
        <>
            <Head title={`${t('owner.report')} — ${title}`} />

            <div className="space-y-8 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <Heading
                        variant="small"
                        title={t('owner.report')}
                        description={title}
                    />

                    <Button
                        asChild
                        variant="outline"
                        className="cursor-pointer"
                    >
                        <a href={`/owner/events/${event.id}/report.csv`}>
                            <Download />
                            {t('owner.download_csv')}
                        </a>
                    </Button>
                </div>

                <Stagger className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Metric
                        label={t('owner.capacity')}
                        value={report.totals.seats_capacity}
                    />
                    <Metric
                        label={t('owner.booked')}
                        value={report.totals.seats_booked}
                    />
                    <Metric
                        label={t('owner.arrived_seats')}
                        value={report.totals.seats_arrived}
                    />
                    <Metric
                        label={t('owner.no_shows')}
                        value={report.rates.no_show_bookings}
                        tone="text-orange-600 dark:text-orange-400"
                    />
                    <Metric
                        label={t('owner.fill_rate')}
                        value={report.rates.fill}
                        suffix="%"
                    />
                    <Metric
                        label={t('owner.attendance_rate')}
                        value={report.rates.attendance}
                        suffix="%"
                    />

                    {!event.is_free && (
                        <>
                            <Metric
                                label={t('owner.collected')}
                                value={money.collected}
                                suffix={money.currency}
                                tone="text-emerald-600 dark:text-emerald-400"
                            />
                            <Metric
                                label={t('owner.outstanding_money')}
                                value={money.outstanding}
                                suffix={money.currency}
                                tone="text-amber-600 dark:text-amber-400"
                            />
                        </>
                    )}
                </Stagger>

                <section className="space-y-3">
                    <h2 className="text-sm font-medium">
                        {t('owner.by_status')}
                    </h2>

                    <ul className="divide-y rounded-xl border">
                        {Object.entries(report.by_status)
                            .filter(([, v]) => v.bookings > 0)
                            .map(([status, v]) => (
                                <li
                                    key={status}
                                    className="flex items-center justify-between gap-4 p-3 text-sm"
                                >
                                    <StatusBadge
                                        status={status as TicketStatus}
                                    />
                                    <span className="text-muted-foreground">
                                        {v.bookings} · {v.seats}
                                    </span>
                                </li>
                            ))}
                    </ul>
                </section>

                {/*
                 * Who wanted in and could not get in. With no mailer in the
                 * product this list is how a venue actually reaches people
                 * when a seat comes back -- and on its own it is the only
                 * measure of demand the event could not meet.
                 */}
                {waiting.length > 0 && (
                    <section className="space-y-3">
                        <div>
                            <h2 className="text-sm font-medium">
                                {t('owner.waiting_list')}
                            </h2>
                            <p className="mt-0.5 text-xs text-muted-foreground">
                                {t('owner.waiting_hint')}
                            </p>
                        </div>

                        <ul className="divide-y rounded-xl border">
                            {waiting.map((person) => (
                                <li
                                    key={person.id}
                                    className="flex flex-wrap items-center justify-between gap-3 p-3 text-sm"
                                >
                                    <span className="min-w-0">
                                        <span className="block truncate font-medium">
                                            {person.full_name}
                                        </span>
                                        <span
                                            className="block text-xs text-muted-foreground"
                                            dir="ltr"
                                        >
                                            {person.phone}
                                        </span>
                                    </span>

                                    <span className="flex items-center gap-3">
                                        {person.notified_at && (
                                            <span className="text-xs text-muted-foreground">
                                                {t('owner.waiting_told')}
                                            </span>
                                        )}

                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <a
                                                href={`https://wa.me/${person.phone.replace(/[^0-9]/g, '')}`}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                <MessageCircle />
                                                {t('common.whatsapp')}
                                            </a>
                                        </Button>
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </>
    );
}

EventReportPage.layout = {
    breadcrumbs: [
        { title: 'owner.events', href: EventController.index() },
        { title: 'owner.report', href: '' },
    ],
};
