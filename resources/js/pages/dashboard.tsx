import { Head, Link } from '@inertiajs/react';
import {
    CalendarClock,
    ScanLine,
    ShieldCheck,
    TrendingDown,
    TrendingUp,
    Store,
    Ticket as TicketIcon,
} from 'lucide-react';
import EventController from '@/actions/App/Http/Controllers/Owner/EventController';
import { EmptyState } from '@/components/empty-state';
import Heading from '@/components/heading';
import { Counter } from '@/components/motion/counter';
import { Stagger, StaggerItem } from '@/components/motion/stagger';
import { SetupChecklist } from '@/components/owner/setup-checklist';
import type { SetupSteps } from '@/components/owner/setup-checklist';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { dateTag } from '@/lib/format';
import { initials } from '@/lib/initials';
import { localised, useLocale } from '@/lib/locale';
import { useTranslation } from '@/lib/translation';
import { dashboard } from '@/routes';
import { owners } from '@/routes/admin';
import { scan, search } from '@/routes/owner';
import type { TicketStatus } from '@/types/public';

type Stats = {
    published_events: number;
    draft_events: number;
    pending: number;
    paid: number;
    seats_paid: number;
    awaiting_seats: number;
    no_show: number;
    attendance: number;
    collected_month: number;
    outstanding: number;
    trend: number | null;
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
    is_draft: boolean;
    total_quantity: number;
    seats_taken: number;
};

type PlatformStats = {
    owners: number;
    events: number;
    tickets: number;
    paid_tickets: number;
    pending_tickets: number;
    seats_paid: number;
    banned: number;
};

type Props = {
    hasPlace: boolean;
    setup: SetupSteps | null;
    platform: PlatformStats | null;
    place?: { name_ar: string; name_en: string; currency: string };
    stats: Stats | null;
    recent: Recent[];
    upcoming: Upcoming[];
};

function Stat({
    label,
    value,
    hint,
    suffix,
    tone,
}: {
    label: string;
    value: number;
    hint?: string;
    suffix?: string;
    tone?: string;
}) {
    return (
        <StaggerItem className="brand-surface rounded-xl border p-4 transition-colors hover:border-primary/40">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className={`mt-1 text-2xl font-bold tabular-nums ${tone ?? ''}`}>
                <Counter value={value} />
                {suffix}
            </p>
            {hint ? (
                <p className="mt-0.5 truncate text-xs text-muted-foreground">
                    {hint}
                </p>
            ) : null}
        </StaggerItem>
    );
}

export default function Dashboard({
    hasPlace,
    setup,
    platform,
    place,
    stats,
    recent,
    upcoming,
}: Props) {
    const { locale } = useLocale();
    const t = useTranslation();
    const dateLocale = dateTag(locale);

    // A super admin owns no venue, so an owner dashboard telling them to
    // "contact the platform administrator" is addressed to themselves. Show
    // them the platform instead.
    if (!hasPlace && platform) {
        return (
            <>
                <Head title={t('dash.title')} />

                <div className="space-y-8 p-4">
                    <div className="flex flex-wrap items-end justify-between gap-3">
                        <Heading
                            variant="small"
                            title={t('dash.title')}
                            description={t('dash.platform_view')}
                        />

                        <Button asChild variant="outline" size="sm">
                            <Link href={owners()}>
                                <ShieldCheck />
                                {t('admin.title')}
                            </Link>
                        </Button>
                    </div>

                    <Stagger className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <Stat
                            label={t('admin.stat_owners')}
                            value={platform.owners}
                        />
                        <Stat
                            label={t('admin.stat_events')}
                            value={platform.events}
                        />
                        <Stat
                            label={t('admin.stat_tickets')}
                            value={platform.tickets}
                        />
                        <Stat
                            label={t('admin.stat_paid')}
                            value={platform.paid_tickets}
                        />
                        <Stat
                            label={t('admin.stat_pending')}
                            value={platform.pending_tickets}
                            tone="text-amber-600 dark:text-amber-400"
                        />
                        <Stat
                            label={t('admin.stat_seats')}
                            value={platform.seats_paid}
                        />
                        <Stat
                            label={t('admin.suspended')}
                            value={platform.banned}
                        />
                    </Stagger>
                </div>
            </>
        );
    }

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

                    <div className="flex items-center gap-2">
                        <span className="inline-flex items-center gap-1.5 rounded-full border border-primary/30 bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary">
                            <span className="relative flex size-1.5">
                                <span className="absolute inline-flex size-full animate-ping rounded-full bg-primary opacity-75 motion-reduce:animate-none" />
                                <span className="relative inline-flex size-1.5 rounded-full bg-primary" />
                            </span>
                            {t('dash.live')}
                        </span>

                        <Button asChild size="sm">
                            <Link href={scan()}>
                                <ScanLine />
                                {t('dash.open_scanner')}
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Only ever present before the venue's first event goes
                    live; it removes itself from then on. */}
                {setup && <SetupChecklist steps={setup} />}

                <section className="brand-surface flex flex-wrap items-end justify-between gap-6 rounded-2xl border p-5 sm:p-6">
                    <div className="min-w-0">
                        <p className="text-sm text-muted-foreground">
                            {t('dash.collected_month')}
                        </p>
                        <p className="mt-1 flex flex-wrap items-baseline gap-2">
                            <span className="text-3xl font-bold tabular-nums sm:text-4xl">
                                <Counter value={stats.collected_month} />
                            </span>
                            <span className="text-sm text-muted-foreground">
                                {place?.currency}
                            </span>
                            {stats.trend !== null ? (
                                <span
                                    className={`inline-flex items-center gap-0.5 text-sm font-medium tabular-nums ${
                                        stats.trend >= 0
                                            ? 'text-primary'
                                            : 'text-destructive'
                                    }`}
                                >
                                    {stats.trend >= 0 ? (
                                        <TrendingUp className="size-4" />
                                    ) : (
                                        <TrendingDown className="size-4" />
                                    )}
                                    {stats.trend >= 0 ? '+' : ''}
                                    {stats.trend}%
                                </span>
                            ) : null}
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t('dash.to_collect', {
                                amount: `${stats.outstanding.toLocaleString('en-US')} ${place?.currency}`,
                            })}
                        </p>
                    </div>

                    <div className="flex gap-6">
                        <div>
                            <p className="text-xs text-muted-foreground">
                                {t('dash.attendance')}
                            </p>
                            <p className="mt-1 text-2xl font-bold tabular-nums">
                                <Counter value={stats.attendance} />%
                            </p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">
                                {t('dash.no_show')}
                            </p>
                            <p className="mt-1 text-2xl font-bold tabular-nums">
                                <Counter value={stats.no_show} />
                            </p>
                        </div>
                    </div>
                </section>

                <Stagger className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Stat
                        label={t('dash.published')}
                        value={stats.published_events}
                    />
                    <Stat label={t('dash.drafts')} value={stats.draft_events} />
                    <Stat
                        label={t('dash.paid')}
                        value={stats.paid}
                        hint={t('dash.seats_n', { n: stats.seats_paid })}
                    />
                    <Stat
                        label={t('dash.pending')}
                        value={stats.pending}
                        hint={t('dash.seats_held', { n: stats.awaiting_seats })}
                        tone="text-amber-600 dark:text-amber-400"
                    />
                </Stagger>

                <div className="grid gap-6 lg:grid-cols-2">
                    <section className="space-y-3">
                        <div className="flex items-center justify-between gap-2">
                            <h2 className="flex items-center gap-2 text-sm font-medium">
                                <TicketIcon className="size-4" />
                                {t('dash.recent')}
                            </h2>
                            <Link
                                href={search()}
                                className="rounded-sm text-xs text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                            >
                                {t('dash.all')}
                            </Link>
                        </div>

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
                                            <span
                                                aria-hidden
                                                className="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary"
                                            >
                                                {initials(ticket.full_name)}
                                            </span>
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
                                                    <span className="flex shrink-0 items-center gap-2 text-xs text-muted-foreground">
                                                        {new Date(
                                                            event.starts_at,
                                                        ).toLocaleDateString(
                                                            dateLocale,
                                                            {
                                                                dateStyle:
                                                                    'medium',
                                                            },
                                                        )}
                                                        {!event.is_draft && (
                                                            <span className="font-semibold text-foreground tabular-nums">
                                                                {pct}%
                                                            </span>
                                                        )}
                                                    </span>
                                                </div>

                                                {event.is_draft ? (
                                                    <p className="mt-2 text-xs text-muted-foreground">
                                                        {t(
                                                            'dash.not_published',
                                                            {
                                                                n: event.total_quantity,
                                                            },
                                                        )}
                                                    </p>
                                                ) : (
                                                    <>
                                                        <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-muted">
                                                            <div
                                                                className="h-full rounded-full transition-[width] duration-500"
                                                                style={{
                                                                    width: `${pct}%`,
                                                                    backgroundColor:
                                                                        'var(--brand-jade-700)',
                                                                }}
                                                            />
                                                        </div>

                                                        <p className="mt-1 text-xs text-muted-foreground">
                                                            {t(
                                                                'dash.of_total',
                                                                {
                                                                    taken: event.seats_taken,
                                                                    total: event.total_quantity,
                                                                    left,
                                                                },
                                                            )}
                                                        </p>
                                                    </>
                                                )}
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
