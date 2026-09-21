import { Link } from '@inertiajs/react';
import { CalendarDays, MapPin } from 'lucide-react';
import { dateTag, formatMoney } from '@/lib/format';
import { localised, useLocale } from '@/lib/locale';
import { useTranslation } from '@/lib/translation';
import { cn } from '@/lib/utils';

export type ListedEvent = {
    slug: string;
    title_ar: string;
    title_en: string;
    starts_at: string;
    cover: string | null;
    is_free: boolean;
    price: number;
    currency: string;
    /** Null when the event has no seat limit. */
    seats_remaining: number | null;
    /** Booking window still open. An event stays listed until it ends. */
    is_open: boolean;
    /** Already happened; shown only as a record, never as an offer. */
    ended?: boolean;
    place_slug: string;
    place_name_ar: string;
    place_name_en: string;
    /** The venue whose room this is, when the event is an organiser's. */
    host_name_ar?: string | null;
    host_name_en?: string | null;
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
    const soldOut =
        event.seats_remaining !== null && event.seats_remaining <= 0;
    const closed = !event.is_open;
    const ended = event.ended === true;

    return (
        <Link
            href={`/${event.place_slug}/${event.slug}`}
            className={cn(
                'group block h-full overflow-hidden rounded-xl border transition focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                ended && 'opacity-70',
            )}
        >
            <div className="relative aspect-video bg-muted">
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
                            {event.host_name_ar
                                ? localised(
                                      locale,
                                      event.host_name_ar,
                                      event.host_name_en ?? null,
                                  )
                                : localised(
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
                            closed || soldOut
                                ? 'size-1.5 rounded-full bg-muted-foreground'
                                : 'size-1.5 rounded-full bg-primary'
                        }
                    />
                    {ended
                        ? t('home.ended')
                        : closed
                          ? t('home.booking_closed')
                          : soldOut
                            ? t('home.sold_out')
                            : event.seats_remaining === null
                              ? t('home.booking_open')
                              : t('home.seats_left', {
                                    n: event.seats_remaining,
                                })}
                </p>
            </div>
        </Link>
    );
}
