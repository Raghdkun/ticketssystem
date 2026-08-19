import { useState } from 'react';
import { cn } from '@/lib/utils';
import type { EventCover as Cover } from '@/types/public';

type Props = {
    cover: Cover;
    alt: string;
    className?: string;
    /** The hero image on the event page; everything else loads lazily. */
    priority?: boolean;
};

/**
 * Responsive event cover.
 *
 * Serves the portrait crop to phones and the landscape crop to wider screens,
 * over the inline blur placeholder generated at upload time so the space is
 * filled immediately instead of flashing empty on a slow connection.
 */
export function EventCover({ cover, alt, className, priority = false }: Props) {
    const [loaded, setLoaded] = useState(false);

    if (!cover?.portrait && !cover?.landscape) {
        return null;
    }

    return (
        <>
            {cover.placeholder && (
                <img
                    src={cover.placeholder}
                    alt=""
                    aria-hidden="true"
                    className={cn(
                        'absolute inset-0 size-full scale-105 object-cover blur-xl transition-opacity duration-500',
                        loaded ? 'opacity-0' : 'opacity-100',
                        className,
                    )}
                />
            )}

            <picture>
                {cover.landscape && (
                    <source
                        media="(min-width: 640px)"
                        srcSet={`/storage/${cover.landscape}`}
                    />
                )}
                <img
                    src={`/storage/${cover.portrait ?? cover.landscape}`}
                    alt={alt}
                    onLoad={() => setLoaded(true)}
                    className={cn(
                        'size-full object-cover transition-opacity duration-500',
                        loaded ? 'opacity-100' : 'opacity-0',
                        className,
                    )}
                    fetchPriority={priority ? 'high' : 'auto'}
                    loading={priority ? 'eager' : 'lazy'}
                    decoding="async"
                />
            </picture>
        </>
    );
}
