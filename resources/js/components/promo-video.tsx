import { Play } from 'lucide-react';
import { useState } from 'react';
import type { PromoVideo as Promo } from '@/types/public';

/**
 * Promo video with a click-to-play poster.
 *
 * Nothing is fetched until the visitor asks for it: a 100 MB autoplay would be
 * hostile on the mobile connections this app is built for. Falls back to the
 * event cover when ffmpeg was unavailable to extract a frame.
 */
export function PromoVideo({
    video,
    poster,
    label,
}: {
    video: Promo;
    poster: string | null;
    label: string;
}) {
    const [playing, setPlaying] = useState(false);
    const posterSrc = video.poster ?? poster;

    if (playing) {
        return (
            <video
                className="size-full object-cover"
                controls
                autoPlay
                playsInline
                preload="metadata"
                poster={posterSrc ? `/storage/${posterSrc}` : undefined}
            >
                <source src={`/storage/${video.src}`} type={video.mime} />
            </video>
        );
    }

    return (
        <button
            type="button"
            onClick={() => setPlaying(true)}
            aria-label={label}
            className="group relative size-full cursor-pointer"
        >
            {posterSrc && (
                <img
                    src={`/storage/${posterSrc}`}
                    alt=""
                    className="size-full object-cover"
                    loading="lazy"
                />
            )}

            <span className="absolute inset-0 flex items-center justify-center bg-black/25 transition-colors group-hover:bg-black/35">
                <span className="flex size-16 items-center justify-center rounded-full bg-white/90 text-neutral-900 shadow-lg transition-transform group-hover:scale-105">
                    <Play
                        className="size-7 translate-x-0.5"
                        fill="currentColor"
                    />
                </span>
            </span>
        </button>
    );
}
