import { Link } from '@inertiajs/react';
import { CalendarDays, Store } from 'lucide-react';
import { useState } from 'react';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { dateTag } from '@/lib/format';
import { localised, useLocale } from '@/lib/locale';
import { useTranslation } from '@/lib/translation';
import type { PublicPlace, SiblingEvent } from '@/types/public';

type Props = { place: PublicPlace; siblings: SiblingEvent[] };

/**
 * Fixed vertical tab on the screen edge carrying the venue's name. Tapping it
 * opens a sheet listing that venue's other open events.
 *
 * Anchors to the inline-start edge, so it sits on the right in Arabic and the
 * left in English without a second stylesheet.
 */
export function PlaceEdgeTab({ place, siblings }: Props) {
    const { locale, direction } = useLocale();
    const t = useTranslation();
    const [open, setOpen] = useState(false);

    const placeName = localised(locale, place.name_ar, place.name_en);
    const side = direction === 'rtl' ? 'right' : 'left';

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                aria-label={placeName}
                className="fixed start-0 top-1/2 z-40 hidden -translate-y-1/2 items-center gap-2 rounded-e-xl bg-neutral-900/90 py-4 ps-2.5 pe-2 text-white shadow-lg backdrop-blur transition hover:bg-neutral-900 md:flex rtl:rounded-s-xl rtl:rounded-e-none dark:bg-white/90 dark:text-neutral-900"
            >
                <Store className="size-4 shrink-0" />
                <span
                    className="max-h-40 overflow-hidden text-xs font-semibold tracking-wide text-ellipsis whitespace-nowrap"
                    style={{ writingMode: 'vertical-rl', rotate: '180deg' }}
                >
                    {placeName}
                </span>
            </button>

            <Sheet open={open} onOpenChange={setOpen}>
                <SheetContent side={side} className="w-[88vw] sm:w-96">
                    <SheetHeader>
                        <SheetTitle className="text-start">
                            {/* The venue's own page: everything it runs, its
                                locations and how to get to them. */}
                            <Link
                                href={`/${place.slug}`}
                                className="rounded-sm underline-offset-4 hover:underline"
                            >
                                {placeName}
                            </Link>
                        </SheetTitle>
                        <SheetDescription className="text-start">
                            {siblings.length > 0
                                ? t('event.other_events')
                                : t('event.no_other_events')}
                        </SheetDescription>
                    </SheetHeader>

                    <ul className="space-y-2 overflow-y-auto px-4 pb-6">
                        {siblings.map((event) => (
                            <li key={event.slug}>
                                <Link
                                    href={`/${place.slug}/${event.slug}`}
                                    className="flex items-center gap-3 rounded-xl border p-3 transition hover:bg-muted/60"
                                    onClick={() => setOpen(false)}
                                >
                                    <span
                                        className="size-12 shrink-0 overflow-hidden rounded-lg"
                                        style={{
                                            backgroundColor:
                                                'var(--brand-jade-700)',
                                        }}
                                    >
                                        {event.cover && (
                                            <img
                                                src={`/storage/${event.cover}`}
                                                alt=""
                                                className="size-full object-cover"
                                                loading="lazy"
                                            />
                                        )}
                                    </span>

                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate text-sm font-medium">
                                            {localised(
                                                locale,
                                                event.title_ar,
                                                event.title_en,
                                            )}
                                        </span>
                                        <span className="mt-0.5 flex items-center gap-1.5 text-xs text-muted-foreground">
                                            <CalendarDays className="size-3.5" />
                                            {new Date(
                                                event.starts_at,
                                            ).toLocaleDateString(
                                                dateTag(locale),
                                                { dateStyle: 'medium' },
                                            )}
                                        </span>
                                    </span>

                                    <span className="shrink-0 text-xs font-semibold">
                                        {event.is_free
                                            ? t('event.free')
                                            : `${event.price.toLocaleString('en-GB')} ${event.currency}`}
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </SheetContent>
            </Sheet>
        </>
    );
}
