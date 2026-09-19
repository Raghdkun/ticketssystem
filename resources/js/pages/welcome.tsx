import { Head, Link, router, usePage } from '@inertiajs/react';
import { CalendarDays, Search, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Wordmark } from '@/components/brand/wordmark';
import { EmptyState } from '@/components/empty-state';
import { EventCard } from '@/components/event-card';
import type { ListedEvent } from '@/components/event-card';
import { FlashToaster } from '@/components/flash-toaster';
import { LanguageToggle } from '@/components/language-toggle';
import { PublicFooter } from '@/components/public-footer';
import { ThemeToggle } from '@/components/theme-toggle';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dateTag, formatNumber } from '@/lib/format';
import { localised, useLocale } from '@/lib/locale';
import { useTranslation } from '@/lib/translation';
import { cn } from '@/lib/utils';

type Venue = {
    slug: string;
    name_ar: string;
    name_en: string;
    events: number;
};

type Props = {
    events: ListedEvent[];
    venues: Venue[];
    filters: { venue: string; q: string };
    total: number;
    limit: number;
};

export default function Welcome({
    events,
    venues,
    filters,
    total,
    limit,
}: Props) {
    const { locale } = useLocale();
    const { platform } = usePage<{
        platform: { name: string; tagline: string | null };
    }>().props;
    const t = useTranslation();

    /*
     * A flat run of every event stops being scannable somewhere around a
     * dozen, so the listing is cut three ways: by venue, by name, and into
     * months. Filtering happens on the server -- the point is that this keeps
     * working at two hundred events, and shipping two hundred to the browser
     * to filter there would defeat it.
     */
    const [query, setQuery] = useState(filters.q);

    // Tracks the value the server was last asked for, so a filter change
    // arriving from elsewhere does not fight what is being typed.
    const requested = useRef(filters.q);

    useEffect(() => {
        if (query === requested.current) {
            return;
        }

        const timer = setTimeout(() => {
            requested.current = query;

            router.get(
                '/',
                { q: query || undefined, venue: filters.venue || undefined },
                {
                    only: ['events', 'total', 'filters'],
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                },
            );
        }, 300);

        return () => clearTimeout(timer);
    }, [query, filters.venue]);

    /*
     * Month headings. The events arrive sorted, so grouping is a single pass
     * and the order the server chose is preserved.
     */
    const months = useMemo(() => {
        const groups: { key: string; label: string; events: ListedEvent[] }[] =
            [];

        for (const event of events) {
            const date = new Date(event.starts_at);
            const key = `${date.getFullYear()}-${date.getMonth()}`;

            if (groups.at(-1)?.key !== key) {
                groups.push({
                    key,
                    label: date.toLocaleDateString(dateTag(locale), {
                        month: 'long',
                        year: 'numeric',
                    }),
                    events: [],
                });
            }

            groups.at(-1)?.events.push(event);
        }

        return groups;
    }, [events, locale]);

    const filtered = filters.venue !== '' || filters.q !== '';
    const showSearch = total > 6 || filters.q !== '';
    const showVenues = venues.length > 1;

    const venueHref = (slug: string) => {
        const params = new URLSearchParams();

        if (slug) {
            params.set('venue', slug);
        }

        if (filters.q) {
            params.set('q', filters.q);
        }

        const search = params.toString();

        return search ? `/?${search}` : '/';
    };

    return (
        <div className="min-h-dvh bg-background">
            <Head title={t('home.whats_on')}>
                <meta name="description" content={t('home.tagline')} />
            </Head>

            <FlashToaster />

            <header className="border-b">
                <div className="mx-auto flex w-full max-w-5xl items-center justify-between gap-4 p-5">
                    <Wordmark className="h-8 text-foreground" />

                    <div className="flex items-center gap-2">
                        <Button asChild size="sm" className="">
                            {/* The label is hidden on narrow screens, so the
                                link carries its own accessible name. */}
                            <Link
                                href="/my-tickets"
                                aria-label={t('home.find_ticket')}
                            >
                                <Search />
                                <span className="hidden sm:inline">
                                    {t('home.find_ticket')}
                                </span>
                            </Link>
                        </Button>
                        <ThemeToggle className="bg-black/10 text-foreground dark:bg-white/10" />
                        <LanguageToggle className="bg-black/10 text-foreground dark:bg-white/10" />
                    </div>
                </div>
            </header>

            <main
                id="main-content"
                className="mx-auto w-full max-w-5xl space-y-10 p-5"
            >
                {/* The wordmark in the header already says who we are, so the
                    hero says what we do. One orange rule, no gradient, no
                    texture: the restraint is the brand. */}
                <section className="rounded-lg border bg-card p-8 sm:p-12">
                    <h1 className="max-w-2xl text-3xl leading-tight font-extrabold sm:text-5xl">
                        {platform.tagline ?? t('home.tagline')}
                    </h1>
                    <div
                        className="mt-5 h-1 w-16 rounded-full bg-primary"
                        aria-hidden="true"
                    />

                    <Button asChild size="lg" className="mt-6 cursor-pointer">
                        <a href="#whats-on">{t('home.whats_on')}</a>
                    </Button>
                </section>

                <section id="whats-on" className="space-y-5">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h2 className="text-2xl font-bold">
                            {t('home.whats_on')}
                        </h2>

                        {total > limit && (
                            <p className="text-sm text-muted-foreground">
                                {t('home.showing', {
                                    shown: formatNumber(events.length),
                                    total: formatNumber(total),
                                })}
                            </p>
                        )}
                    </div>

                    {/* Each half earns its space separately: searching is
                        only worth offering once there is something to search
                        through, but a second venue is reason enough to be
                        able to pick one. */}
                    {(showSearch || showVenues) && (
                        <div className="space-y-3">
                            {showSearch && (
                                <div className="relative">
                                    <Search
                                        aria-hidden="true"
                                        className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                                    />
                                    <Input
                                        type="search"
                                        value={query}
                                        onChange={(e) =>
                                            setQuery(e.target.value)
                                        }
                                        aria-label={t('home.search')}
                                        placeholder={t(
                                            'home.search_placeholder',
                                        )}
                                        className="ps-9"
                                    />
                                </div>
                            )}

                            {showVenues && (
                                <nav
                                    aria-label={t('home.all_venues')}
                                    className="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1"
                                >
                                    <VenueChip
                                        href={venueHref('')}
                                        active={filters.venue === ''}
                                        label={t('home.all_venues')}
                                    />
                                    {venues.map((venue) => (
                                        <VenueChip
                                            key={venue.slug}
                                            href={venueHref(venue.slug)}
                                            active={
                                                filters.venue === venue.slug
                                            }
                                            label={localised(
                                                locale,
                                                venue.name_ar,
                                                venue.name_en,
                                            )}
                                            count={venue.events}
                                        />
                                    ))}
                                </nav>
                            )}
                        </div>
                    )}

                    {events.length === 0 ? (
                        <EmptyState
                            icon={CalendarDays}
                            title={
                                filtered ? t('home.no_matches') : t('home.none')
                            }
                            action={
                                filtered ? (
                                    <Button asChild variant="outline">
                                        <Link href="/">
                                            <X />
                                            {t('home.clear_filters')}
                                        </Link>
                                    </Button>
                                ) : undefined
                            }
                        />
                    ) : (
                        <div className="space-y-8">
                            {months.map((month) => (
                                <section key={month.key} className="space-y-4">
                                    {/* Month headings only earn their space
                                        once the listing actually spans more
                                        than one. */}
                                    {months.length > 1 && (
                                        <h3 className="border-b pb-2 text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                                            {month.label}
                                        </h3>
                                    )}

                                    <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                        {month.events.map((event) => (
                                            <li
                                                key={`${event.place_slug}/${event.slug}`}
                                            >
                                                <EventCard event={event} />
                                            </li>
                                        ))}
                                    </ul>
                                </section>
                            ))}
                        </div>
                    )}
                </section>
            </main>

            <PublicFooter />
        </div>
    );
}

function VenueChip({
    href,
    active,
    label,
    count,
}: {
    href: string;
    active: boolean;
    label: string;
    count?: number;
}) {
    return (
        <Link
            href={href}
            preserveScroll
            aria-current={active ? 'true' : undefined}
            className={cn(
                'inline-flex min-h-11 shrink-0 items-center gap-1.5 rounded-full border px-4 text-sm transition',
                active
                    ? 'border-primary bg-primary text-primary-foreground'
                    : 'hover:bg-muted',
            )}
        >
            {label}
            {count !== undefined && (
                <span
                    className={cn(
                        'text-xs tabular-nums',
                        active ? 'opacity-80' : 'text-muted-foreground',
                    )}
                >
                    {formatNumber(count)}
                </span>
            )}
        </Link>
    );
}
