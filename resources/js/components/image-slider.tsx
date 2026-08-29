import { ChevronLeft, ChevronRight, ImageIcon } from 'lucide-react';
import { useRef, useState } from 'react';
import { useLocale } from '@/lib/locale';
import { useTranslation } from '@/lib/translation';
import { cn } from '@/lib/utils';

/**
 * A swipeable strip of photos.
 *
 * Built on native scroll-snap rather than a carousel library: it is a handful
 * of CSS properties, it drags with a finger for free, and it degrades to a
 * plain scrolling row if the script never runs. The arrows are an addition for
 * pointer users, not the mechanism.
 */
export function ImageSlider({
    images,
    className,
    alt,
}: {
    images: string[];
    className?: string;
    alt: string;
}) {
    const t = useTranslation();
    const { direction } = useLocale();
    const track = useRef<HTMLDivElement>(null);
    const [index, setIndex] = useState(0);

    if (images.length === 0) {
        return null;
    }

    // "Next" is leftwards in Arabic, and scrollLeft counts the other way in a
    // right-to-left container, so the sign has to follow the document.
    const step = (delta: number) => {
        const el = track.current;

        if (!el) {
            return;
        }

        const next = Math.min(Math.max(index + delta, 0), images.length - 1);
        const rtl = direction === 'rtl';

        el.scrollTo({
            left: (rtl ? -1 : 1) * next * el.clientWidth,
            behavior: 'smooth',
        });
        setIndex(next);
    };

    return (
        <div className={cn('relative', className)}>
            <div
                ref={track}
                onScroll={(event) => {
                    const el = event.currentTarget;
                    const width = el.clientWidth || 1;
                    setIndex(Math.round(Math.abs(el.scrollLeft) / width));
                }}
                className="flex snap-x snap-mandatory [scrollbar-width:none] overflow-x-auto scroll-smooth rounded-xl [&::-webkit-scrollbar]:hidden"
            >
                {images.map((src, i) => (
                    <img
                        key={src}
                        src={src}
                        alt={`${alt} — ${i + 1}/${images.length}`}
                        loading={i === 0 ? 'eager' : 'lazy'}
                        className="aspect-[4/3] w-full shrink-0 snap-center object-cover"
                    />
                ))}
            </div>

            {images.length > 1 && (
                <>
                    <button
                        type="button"
                        onClick={() => step(-1)}
                        disabled={index === 0}
                        aria-label={t('common.previous')}
                        className="absolute start-2 top-1/2 flex size-9 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full bg-black/45 text-white backdrop-blur transition-opacity hover:bg-black/60 disabled:opacity-0"
                    >
                        {direction === 'rtl' ? (
                            <ChevronRight className="size-5" />
                        ) : (
                            <ChevronLeft className="size-5" />
                        )}
                    </button>

                    <button
                        type="button"
                        onClick={() => step(1)}
                        disabled={index === images.length - 1}
                        aria-label={t('common.next')}
                        className="absolute end-2 top-1/2 flex size-9 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full bg-black/45 text-white backdrop-blur transition-opacity hover:bg-black/60 disabled:opacity-0"
                    >
                        {direction === 'rtl' ? (
                            <ChevronLeft className="size-5" />
                        ) : (
                            <ChevronRight className="size-5" />
                        )}
                    </button>

                    {/* Position, not a control: tapping a dot on a strip this
                        small is a worse target than swiping. */}
                    <div
                        aria-hidden
                        className="absolute inset-x-0 bottom-2 flex justify-center gap-1.5"
                    >
                        {images.map((src, i) => (
                            <span
                                key={src}
                                className={cn(
                                    'size-1.5 rounded-full transition-colors',
                                    i === index ? 'bg-white' : 'bg-white/45',
                                )}
                            />
                        ))}
                    </div>
                </>
            )}
        </div>
    );
}

/** Placeholder for a location with no photos yet. */
export function ImageSliderEmpty({ label }: { label: string }) {
    return (
        <div className="flex aspect-[4/3] w-full items-center justify-center rounded-xl border border-dashed bg-muted/40">
            <span className="flex flex-col items-center gap-2 text-xs text-muted-foreground">
                <ImageIcon className="size-6" aria-hidden />
                {label}
            </span>
        </div>
    );
}
