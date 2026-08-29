import { Link } from '@inertiajs/react';
import { CalendarDays, MapPin } from 'lucide-react';
import { dateTag, formatMoney } from '@/lib/format';
import { localised, useLocale } from '@/lib/locale';
import { useTranslation } from '@/lib/translation';

export type ListedEvent = {
    slug: string;
    title_ar: string;
    title_en: string;
    starts_at: string;
    cover: string | null;
    is_free: boolean;
    price: number;
    currency: string;
    seats_remaining: number;
    place_slug: string;
    place_name_ar: string;
    place_name_en: string;
};

/**
 * One event in a listing. Shared by the home page and a venue's own page so
 * the same event looks the same wherever it is met.
 */
export function EventCard({
    event,
    showVenue = true,
}: {
    event: ListedEvent;
    showVenue?: boolean;
}) {
    const { locale } = useLocale();
    const t = useTranslation();
    const soldOut = event.seats_remaining <= 0;

    return (
        <Link
            href={`/${event.place_slug}/${event.slug}`}
            className="group block h-full overflow-hidden rounded-xl border transition hover:shadow-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            <div
                className="relative aspect-video"
                style={{ backgroundColor: 'var(--brand-jade-700)' }}
            >
                {event.cover && (
                    <img
                        src={`/storage/${event.cover}`}
                        alt=""
                        className="size-full object-cover"
                        loading="lazy"
                        decoding="async"
                    />
                )}
            </div>

            <div className="space-y-2 p-4">
                <h3 className="line-clamp-2 leading-tight font-medium">
                    {localised(locale, event.title_ar, event.title_en)}
                </h3>

                {showVenue && (
                    <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                        <MapPin className="size-3.5 shrink-0" />
                        <span className="truncate">
                            {localised(
                                locale,
                                event.place_name_ar,
                                event.place_name_en,
                            )}
                        </span>
                    </p>
                )}

                <div className="flex flex-wrap items-center justify-between gap-2 pt-1">
                    <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
                        <CalendarDays className="size-3.5" />
                        {new Date(event.starts_at).toLocaleDateString(
                            dateTag(locale),
                            { dateStyle: 'medium' },
                        )}
                    </span>

                    <span className="text-sm font-semibold">
                        {event.is_free
                            ? t('event.free')
                            : formatMoney(event.price, event.currency)}
                    </span>
                </div>

                {/* Status is a dot plus text, never colour alone. */}
                <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                    <span
                        aria-hidden="true"
                        className={
                            soldOut
                                ? 'size-1.5 rounded-full bg-muted-foreground'
                                : 'size-1.5 rounded-full bg-primary'
                        }
                    />
                    {soldOut
                        ? t('home.sold_out')
                        : t('home.seats_left', { n: event.seats_remaining })}
                </p>
            </div>
        </Link>
    );
}
