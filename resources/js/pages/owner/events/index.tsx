import { Head, Link } from '@inertiajs/react';
import { BarChart3, ImageIcon, Plus, Printer, Sparkles } from 'lucide-react';
import EventController from '@/actions/App/Http/Controllers/Owner/EventController';
import Heading from '@/components/heading';
import { Stagger, StaggerItem } from '@/components/motion/stagger';
import { RepeatEvent } from '@/components/owner/repeat-event';
import { Button } from '@/components/ui/button';
import { dateTag } from '@/lib/format';
import { localised, useLocale } from '@/lib/locale';
import { useTranslation } from '@/lib/translation';

type EventRow = {
    id: number;
    slug: string;
    title_ar: string;
    title_en: string;
    status: string;
    starts_at: string;
    total_quantity: number;
    seats_taken: number;
    tickets_count: number;
    cover: string | null;
};

type Props = {
    place: { name_ar: string; name_en: string; slug: string } | null;
    events: EventRow[];
    counts: { all: number; published: number; draft: number };
    filter: string;
};

const FILTERS = [
    { key: 'all', label: 'owner.filter_all' },
    { key: 'published', label: 'event.status.published' },
    { key: 'draft', label: 'event.status.draft' },
] as const;

/**
 * Status reads as a dot plus a word, never colour alone -- the same rule the
 * door uses, because some readers are colourblind.
 */
function StatusChip({ status, label }: { status: string; label: string }) {
    return (
        <span className="inline-flex items-center gap-1.5 rounded-full bg-card/90 px-2.5 py-1 text-xs font-medium shadow-sm backdrop-blur-sm">
            <span
                className="size-1.5 rounded-full"
                style={{
                    backgroundColor:
                        status === 'published'
                            ? 'var(--brand-jade-500)'
                            : 'var(--brand-basalt-400)',
                }}
            />
            {label}
        </span>
    );
}

export default function EventsIndex({ place, events, counts, filter }: Props) {
    const { locale } = useLocale();
    const t = useTranslation();
    const dateLocale = dateTag(locale);

    return (
        <>
            <Head title={t('owner.events')} />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        variant="small"
                        title={t('owner.events')}
                        description={
                            place
                                ? localised(
                                      locale,
                                      place.name_ar,
                                      place.name_en,
                                  )
                                : t('dash.no_place')
                        }
                    />

                    {place && (
                        <div className="flex flex-wrap items-center gap-3">
                            <div className="flex items-center gap-1 rounded-full border p-1">
                                {FILTERS.map((option) => (
                                    <Link
                                        key={option.key}
                                        href={
                                            option.key === 'all'
                                                ? '/owner/events'
                                                : `/owner/events?status=${option.key}`
                                        }
                                        preserveScroll
                                        aria-current={
                                            filter === option.key
                                                ? 'page'
                                                : undefined
                                        }
                                        className={`rounded-full px-3 py-1.5 text-xs font-medium transition-colors ${
                                            filter === option.key
                                                ? 'bg-primary text-primary-foreground'
                                                : 'text-muted-foreground hover:text-foreground'
                                        }`}
                                    >
                                        {t(option.label)}{' '}
                                        <span className="tabular-nums">
                                            {
                                                counts[
                                                    option.key as keyof typeof counts
                                                ]
                                            }
                                        </span>
                                    </Link>
                                ))}
                            </div>

                            <Button asChild>
                                <Link href={EventController.create()}>
                                    <Plus />
                                    {t('owner.new_event')}
                                </Link>
                            </Button>
                        </div>
                    )}
                </div>

                {events.length === 0 ? (
                    <div className="rounded-xl border border-dashed p-12 text-center">
                        <p className="text-sm text-muted-foreground">
                            {place
                                ? t('owner.no_events')
                                : t('owner.no_place_events')}
                        </p>
                    </div>
                ) : (
                    <Stagger className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {events.map((event) => {
                            const draft = event.status === 'draft';
                            const pct = Math.min(
                                100,
                                Math.round(
                                    (event.seats_taken /
                                        Math.max(1, event.total_quantity)) *
                                        100,
                                ),
                            );
                            const date = new Date(
                                event.starts_at,
                            ).toLocaleDateString(dateLocale, {
                                day: 'numeric',
                                month: 'long',
                            });

                            return (
                                <StaggerItem key={event.id} className="h-full">
                                    {/* The card body is one link, but the footer
                                        actions are links too, and anchors cannot
                                        nest. A stretched overlay keeps the whole
                                        card clickable without nesting. */}
                                    <article className="brand-surface group relative flex h-full flex-col overflow-hidden rounded-xl border transition-all duration-200 hover:border-primary/40 hover:shadow-md">
                                        <div className="relative grid aspect-video place-items-center bg-muted">
                                            {event.cover ? (
                                                <img
                                                    src={`/storage/${event.cover}`}
                                                    alt=""
                                                    className="absolute inset-0 size-full object-cover"
                                                    loading="lazy"
                                                />
                                            ) : (
                                                <ImageIcon
                                                    className="size-7 text-muted-foreground/60"
                                                    aria-hidden
                                                />
                                            )}

                                            <span className="absolute start-3 top-3">
                                                <StatusChip
                                                    status={event.status}
                                                    label={t(
                                                        `event.status.${event.status}`,
                                                    )}
                                                />
                                            </span>
                                        </div>

                                        <div className="flex flex-1 flex-col gap-2 p-4">
                                            <Link
                                                href={EventController.edit(
                                                    event.id,
                                                )}
                                                className="rounded-sm leading-tight font-semibold after:absolute after:inset-0 after:content-['']"
                                            >
                                                {localised(
                                                    locale,
                                                    event.title_ar,
                                                    event.title_en,
                                                )}
                                            </Link>

                                            <p
                                                className="truncate text-sm text-muted-foreground"
                                                dir={
                                                    locale === 'ar'
                                                        ? 'ltr'
                                                        : 'rtl'
                                                }
                                            >
                                                {locale === 'ar'
                                                    ? event.title_en
                                                    : event.title_ar}
                                            </p>

                                            {draft ? (
                                                <p className="mt-auto pt-2 text-xs text-muted-foreground">
                                                    {t('owner.draft_meta', {
                                                        n: event.total_quantity,
                                                        date,
                                                    })}
                                                </p>
                                            ) : (
                                                <div className="mt-auto pt-2">
                                                    <div className="flex items-center gap-2">
                                                        <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-muted">
                                                            <div
                                                                className="h-full rounded-full"
                                                                style={{
                                                                    width: `${pct}%`,
                                                                    backgroundColor:
                                                                        'var(--brand-jade-700)',
                                                                }}
                                                            />
                                                        </div>
                                                        <span className="text-xs font-semibold tabular-nums">
                                                            {pct}%
                                                        </span>
                                                    </div>

                                                    <p className="mt-1.5 text-xs text-muted-foreground">
                                                        {t('owner.seats_meta', {
                                                            taken: event.seats_taken,
                                                            total: event.total_quantity,
                                                            date,
                                                        })}
                                                    </p>
                                                </div>
                                            )}
                                        </div>

                                        {/* The poster workshop and the repeat
                                            control belong on every card. Both
                                            were reachable only from part-way
                                            down the edit form, which is a long
                                            way to bury the most distinctive
                                            thing an owner can do here. */}
                                        <div className="relative z-10 flex flex-wrap items-center gap-x-4 gap-y-2 border-t px-4 py-2.5">
                                            {draft ? (
                                                <Link
                                                    href={EventController.edit(
                                                        event.id,
                                                    )}
                                                    className="inline-flex items-center gap-1.5 text-xs font-medium text-primary underline-offset-4 hover:underline"
                                                >
                                                    {t('owner.finish_publish')}
                                                </Link>
                                            ) : (
                                                <>
                                                    <a
                                                        href={`/owner/events/${event.id}/report`}
                                                        className="inline-flex items-center gap-1.5 text-xs text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                                                    >
                                                        <BarChart3 className="size-3.5" />
                                                        {t('owner.report')}
                                                    </a>

                                                    <a
                                                        href={`/owner/events/${event.id}/door-sheet`}
                                                        className="inline-flex items-center gap-1.5 text-xs text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                                                    >
                                                        <Printer className="size-3.5" />
                                                        {t('owner.door_sheet')}
                                                    </a>
                                                </>
                                            )}

                                            <Link
                                                href={`/owner/events/${event.id}/poster`}
                                                className="inline-flex items-center gap-1.5 text-xs text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                                            >
                                                <Sparkles className="size-3.5" />
                                                {t('owner.poster')}
                                            </Link>

                                            <span className="ms-auto">
                                                <RepeatEvent
                                                    eventId={event.id}
                                                />
                                            </span>
                                        </div>
                                    </article>
                                </StaggerItem>
                            );
                        })}
                    </Stagger>
                )}
            </div>
        </>
    );
}

EventsIndex.layout = {
    breadcrumbs: [{ title: 'owner.events', href: EventController.index() }],
};
