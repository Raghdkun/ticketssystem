import { MapPin, Navigation } from 'lucide-react';
import { useState, useSyncExternalStore } from 'react';
import { ImageSlider } from '@/components/image-slider';
import { MapCanvas } from '@/components/map/map-canvas';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { localised, useLocale } from '@/lib/locale';
import { useTranslation } from '@/lib/translation';

export type VenueLocation = {
    name: string;
    lat: number | null;
    lng: number | null;
    address_ar: string | null;
    address_en: string | null;
    landmark_ar: string | null;
    landmark_en: string | null;
    images: string[];
};

/**
 * Where an event happens, and what tapping its name opens.
 *
 * With nothing on file the name is plain text — a control that opens an empty
 * sheet is a worse answer than no control at all.
 */
export function VenueLink({
    name,
    location,
}: {
    name: string;
    location: VenueLocation | null;
}) {
    const t = useTranslation();
    const { locale } = useLocale();
    const [open, setOpen] = useState(false);

    // A `geo:` URL is the right handoff on a phone — it opens whichever maps
    // app is installed rather than assuming Google. On a desktop nothing
    // handles the scheme, so the button would silently do nothing at all.
    const coarsePointer = useSyncExternalStore(
        () => () => {},
        () =>
            typeof window !== 'undefined' &&
            window.matchMedia('(pointer: coarse)').matches,
        () => false,
    );

    if (!location) {
        return <span>{name}</span>;
    }

    const address = localised(
        locale,
        location.address_ar ?? '',
        location.address_en ?? '',
    );
    const landmark = localised(
        locale,
        location.landmark_ar ?? '',
        location.landmark_en ?? '',
    );
    const pinned = location.lat !== null && location.lng !== null;

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="inline-flex cursor-pointer items-center gap-1.5 rounded-sm underline-offset-4 hover:underline"
            >
                <MapPin className="size-4 shrink-0" />
                {location.name || name}
            </button>

            <Sheet open={open} onOpenChange={setOpen}>
                <SheetContent
                    side="bottom"
                    className="max-h-[90dvh] overflow-y-auto"
                >
                    {/* A bottom sheet spans the viewport, which on a desktop is
                        far wider than this content wants to be read at. */}
                    <div className="mx-auto w-full max-w-xl">
                        <SheetHeader>
                            <SheetTitle>{location.name || name}</SheetTitle>
                            <SheetDescription>
                                {address || t('place.no_address')}
                            </SheetDescription>
                        </SheetHeader>

                        <div className="space-y-4 px-4 pb-6">
                            {location.images.length > 0 && (
                                <ImageSlider
                                    images={location.images}
                                    alt={location.name || name}
                                    className="overflow-hidden rounded-xl border"
                                />
                            )}

                            {/* Mounted only once the sheet opens, so a visitor
                                who never taps the name never downloads
                                Leaflet. */}
                            {open && pinned && (
                                <MapCanvas
                                    center={{
                                        lat: location.lat as number,
                                        lng: location.lng as number,
                                    }}
                                    ariaLabel={t('place.map_of', { name })}
                                    className="h-64 w-full overflow-hidden rounded-xl border"
                                />
                            )}

                            {landmark && (
                                <p className="rounded-xl bg-muted/60 p-3 text-sm">
                                    <span className="font-medium">
                                        {t('place.landmark')}:{' '}
                                    </span>
                                    {landmark}
                                </p>
                            )}

                            {pinned && (
                                <>
                                    <Button asChild className="w-full">
                                        <a
                                            href={
                                                coarsePointer
                                                    ? `geo:${location.lat},${location.lng}?q=${location.lat},${location.lng}(${encodeURIComponent(location.name || name)})`
                                                    : `https://www.openstreetmap.org/directions?to=${location.lat},${location.lng}`
                                            }
                                            target={
                                                coarsePointer
                                                    ? undefined
                                                    : '_blank'
                                            }
                                            rel="noreferrer noopener"
                                        >
                                            <Navigation />
                                            {t('place.directions')}
                                        </a>
                                    </Button>

                                    <a
                                        href={`https://www.openstreetmap.org/?mlat=${location.lat}&mlon=${location.lng}#map=17/${location.lat}/${location.lng}`}
                                        target="_blank"
                                        rel="noreferrer noopener"
                                        className="block rounded-sm text-center text-xs text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                                    >
                                        {t('place.open_in_osm')}
                                    </a>
                                </>
                            )}
                        </div>
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}
