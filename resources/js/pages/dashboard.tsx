import { Head, Link } from '@inertiajs/react';
import {
    CalendarClock,
    ScanLine,
    Store,
    Ticket as TicketIcon,
} from 'lucide-react';
import EventController from '@/actions/App/Http/Controllers/Owner/EventController';
import { EmptyState } from '@/components/empty-state';
import Heading from '@/components/heading';
import { Counter } from '@/components/motion/counter';
import { Stagger, StaggerItem } from '@/components/motion/stagger';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { localised, useLocale } from '@/lib/locale';
import { useTranslation } from '@/lib/translation';
import { dashboard } from '@/routes';
import { scan } from '@/routes/owner';
import type { TicketStatus } from '@/types/public';

type Stats = {
    published_events: number;
    draft_events: number;
    pending: number;
    paid: number;
    seats_paid: number;
    awaiting_seats: number;
};

type Recent = {
    token: string;
    full_name: string;
    quantity: number;
    status: TicketStatus;
    created_at: string | null;
    event_title_ar: string;
    event_title_en: string;
};

type Upcoming = {
    id: number;
    title_ar: string;
    title_en: string;
    starts_at: string;
    total_quantity: number;
    seats_taken: number;
    primary_color: string;
};

type Props = {
    hasPlace: boolean;
    place?: { name_ar: string; name_en: string };
    stats: Stats | null;
    recent: Recent[];
    upcoming: Upcoming[];
};

function Stat({
    label,
    value,
    tone,
}: {
    label: string;
    value: number;
    tone?: string;
}) {
    return (
        <StaggerItem className="brand-surface rounded-xl border p-4 transition-colors hover:border-primary/40">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className={`mt-1 text-2xl font-bold tabular-nums ${tone ?? ''}`}>
                <Counter value={value} />
            </p>
        </StaggerItem>
    );
}

export default function Dashboard({
    hasPlace,
    place,
    stats,
    recent,
    upcoming,
}: Props) {
    const { locale } = useLocale();
    const t = useTranslation();
    const dateLocale = locale === 'ar' ? 'ar-SY' : 'en-GB';

    if (!hasPlace || !stats) {
        return (
            <>
                <Head title={t('dash.title')} />
                <div className="p-4">
                    <EmptyState icon={Store} title={t('dash.no_place')} />
                </div>
            </>
        );
    }

    return (
        <>
            <Head title={t('dash.title')} />

            <div className="space-y-8 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <Heading
                        variant="small"
                        title={t('dash.title')}
                        description={
                            place
                                ? localised(
                                      locale,
                                      place.name_ar,
                                      place.name_en,
                                  )
                                : undefined
                        }
                    />

                    <Button asChild variant="outline" size="sm">
                        <Link href={scan()}>
                            <ScanLine />
                            {t('dash.verify_now')}
                        </Link>
                    </Button>
                </div>

                <Stagger className="grid grid-cols-2 gap-3 lg:grid-cols-3">
                    <Stat
                        label={t('dash.published')}
                        value={stats.published_events}
                    />
                    <Stat label={t('dash.drafts')} value={stats.draft_events} />
                    <Stat label={t('dash.paid')} value={stats.paid} />
                    <Stat
                        label={t('dash.pending')}
                        value={stats.pending}
                        tone="text-amber-600 dark:text-amber-400"
                    />
                    <Stat
                        label={t('dash.seats_paid')}
                        value={stats.seats_paid}
                    />
                    <Stat
                        label={t('dash.awaiting_seats')}
                        value={stats.awaiting_seats}
                        tone="text-amber-600 dark:text-amber-400"
                    />
                </Stagger>

                <div className="grid gap-6 lg:grid-cols-2">
                    <section className="space-y-3">
                        <h2 className="flex items-center gap-2 text-sm font-medium">
                            <TicketIcon className="size-4" />
                            {t('dash.recent')}
                        </h2>

                        {recent.length === 0 ? (
                            <EmptyState
                                icon={TicketIcon}
                                title={t('dash.no_recent')}
                            />
                        ) : (
                            <ul className="divide-y rounded-xl border">
                                {recent.map((ticket) => (
                                    <li key={ticket.token}>
                                        <Link
                                            href={`/verify/${ticket.token}`}
                                            className="flex cursor-pointer items-center gap-3 p-3 transition-colors duration-200 hover:bg-muted/50"
                                        >
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate text-sm font-medium">
                                                    {ticket.full_name}
                                                </span>
                                                <span className="block truncate text-xs text-muted-foreground">
                                                    {localised(
                                                        locale,
                                                        ticket.event_title_ar,
                                                        ticket.event_title_en,
                                                    )}
                                                </span>
                                            </span>

                                            <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                                                ×{ticket.quantity}
                                            </span>
                                            <StatusBadge
                                                status={ticket.status}
                                            />
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>

                    <section className="space-y-3">
                        <h2 className="flex items-center gap-2 text-sm font-medium">
                            <CalendarClock className="size-4" />
                            {t('dash.upcoming')}
                        </h2>

                        {upcoming.length === 0 ? (
                            <EmptyState
                                icon={CalendarClock}
                                title={t('dash.no_upcoming')}
                            />
                        ) : (
                            <ul className="space-y-2">
                                {upcoming.map((event) => {
                                    const left = Math.max(
                                        0,
                                        event.total_quantity -
                                            event.seats_taken,
                                    );
                                    const pct = Math.min(
                                        100,
                                        Math.round(
                                            (event.seats_taken /
                                                Math.max(
                                                    1,
                                                    event.total_quantity,
                                                )) *
                                                100,
                                        ),
                                    );

                                    return (
                                        <li key={event.id}>
                                            <Link
                                                href={EventController.edit(
                                                    event.id,
                                                )}
                                                className="block cursor-pointer rounded-xl border p-3 transition-colors duration-200 hover:border-primary/40 hover:bg-muted/50"
                                            >
                                                <div className="flex items-center justify-between gap-3">
                                                    <span className="min-w-0 truncate text-sm font-medium">
                                                        {localised(
                                                            locale,
                                                            event.title_ar,
                                                            event.title_en,
                                                        )}
                                                    </span>
                                                    <span className="shrink-0 text-xs text-muted-foreground">
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
                                                </div>

                                                <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-muted">
                                                    <div
                                                        className="h-full rounded-full"
                                                        style={{
                                                            width: `${pct}%`,
                                                            backgroundColor:
                                                                event.primary_color,
                                                        }}
                                                    />
                                                </div>

                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {t('dash.seats_left', {
                                                        left,
                                                    })}
                                                </p>
                                            </Link>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </section>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'dash.title', href: dashboard() }],
};
