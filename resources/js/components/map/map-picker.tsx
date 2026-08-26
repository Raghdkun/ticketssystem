import { Crosshair, LoaderCircle, MapPin, Search, X } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useLocale } from '@/lib/locale';
import { useTranslation } from '@/lib/translation';
import { MapCanvas, SUWAYDA } from './map-canvas';
import type { LatLng } from './map-canvas';

type Props = {
    value: LatLng | null;
    onChange: (position: LatLng | null) => void;
};

type Hit = { label: string; lat: number; lng: number };

/**
 * Lets an owner put a pin on their venue.
 *
 * Search runs only on an explicit submit rather than as-you-type: Nominatim is
 * a free service that asks for at most one request a second, and an owner sets
 * this once. Dragging the pin is the authoritative step -- geocoding gets you
 * to the street, the owner gets you to the door.
 */
export function MapPicker({ value, onChange }: Props) {
    const t = useTranslation();
    const { locale } = useLocale();
    const [query, setQuery] = useState('');
    const [hits, setHits] = useState<Hit[]>([]);
    const [searching, setSearching] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const centre = value ?? SUWAYDA;

    async function search() {
        if (query.trim().length < 3) {
            return;
        }

        setSearching(true);
        setError(null);

        try {
            const url = new URL('https://nominatim.openstreetmap.org/search');
            url.searchParams.set('format', 'jsonv2');
            url.searchParams.set('limit', '5');
            // Biased to Syria: an owner searching "الساحة" means the one here.
            url.searchParams.set('countrycodes', 'sy');
            url.searchParams.set('accept-language', locale);
            url.searchParams.set('q', query.trim());

            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error('search failed');
            }

            const results = (await response.json()) as {
                display_name: string;
                lat: string;
                lon: string;
            }[];

            setHits(
                results.map((hit) => ({
                    label: hit.display_name,
                    lat: Number(hit.lat),
                    lng: Number(hit.lon),
                })),
            );

            if (results.length === 0) {
                setError(t('place.no_results'));
            }
        } catch {
            // The venue can still be pinned by hand, so a failed lookup is a
            // hint rather than a blocked form.
            setError(t('place.search_failed'));
        } finally {
            setSearching(false);
        }
    }

    function locate() {
        if (!('geolocation' in navigator)) {
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) =>
                onChange({
                    lat: position.coords.latitude,
                    lng: position.coords.longitude,
                }),
            () => setError(t('place.locate_failed')),
        );
    }

    return (
        <div className="space-y-3">
            <div className="flex flex-wrap gap-2">
                <div className="relative min-w-0 flex-1">
                    <Search className="pointer-events-none absolute inset-y-0 start-3 my-auto size-4 text-muted-foreground" />
                    <Input
                        type="search"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        onKeyDown={(event) => {
                            // The picker sits inside the venue form; Enter here
                            // must search, not submit the whole page.
                            if (event.key === 'Enter') {
                                event.preventDefault();
                                void search();
                            }
                        }}
                        placeholder={t('place.search_placeholder')}
                        className="ps-9"
                        aria-label={t('place.search_placeholder')}
                    />
                </div>

                <Button
                    type="button"
                    variant="outline"
                    onClick={() => void search()}
                    disabled={searching || query.trim().length < 3}
                    className="cursor-pointer"
                >
                    {searching ? (
                        <LoaderCircle className="animate-spin" />
                    ) : (
                        <Search />
                    )}
                    {t('place.search')}
                </Button>

                <Button
                    type="button"
                    variant="outline"
                    onClick={locate}
                    className="cursor-pointer"
                >
                    <Crosshair />
                    {t('place.use_my_location')}
                </Button>
            </div>

            {hits.length > 0 && (
                <ul className="divide-y overflow-hidden rounded-xl border">
                    {hits.map((hit) => (
                        <li key={`${hit.lat},${hit.lng}`}>
                            <button
                                type="button"
                                onClick={() => {
                                    onChange({ lat: hit.lat, lng: hit.lng });
                                    setHits([]);
                                }}
                                className="flex w-full cursor-pointer items-start gap-2 p-3 text-start text-sm transition-colors hover:bg-muted/60"
                            >
                                <MapPin className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                {hit.label}
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            {error && <p className="text-xs text-destructive">{error}</p>}

            <MapCanvas
                center={centre}
                interactive
                onMove={onChange}
                ariaLabel={t('place.map_label')}
                markerLabel={t('place.marker_label')}
                className="h-72 w-full overflow-hidden rounded-xl border"
            />

            <div className="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
                <p>
                    {value
                        ? t('place.pinned_at', {
                              lat: value.lat.toFixed(5),
                              lng: value.lng.toFixed(5),
                          })
                        : t('place.tap_to_pin')}
                </p>

                {value && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => onChange(null)}
                        className="cursor-pointer"
                    >
                        <X />
                        {t('place.clear_pin')}
                    </Button>
                )}
            </div>
        </div>
    );
}
