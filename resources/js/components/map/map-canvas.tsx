import type { Map as LeafletMap, Marker } from 'leaflet';
import { useEffect, useRef } from 'react';

export type LatLng = { lat: number; lng: number };

/** As-Suwayda city centre: where a picker with no pin yet should open. */
export const SUWAYDA: LatLng = { lat: 32.7094, lng: 36.5694 };

/**
 * A jade pin drawn as a `divIcon`.
 *
 * Leaflet's default marker resolves its PNGs relative to the stylesheet, which
 * a bundler rewrites and breaks. Inline SVG sidesteps that entirely and lets
 * the pin carry the brand colour.
 */
const PIN = `
<svg width="30" height="42" viewBox="0 0 30 42" fill="none" xmlns="http://www.w3.org/2000/svg">
  <path d="M15 41c0 0 13-15.5 13-26A13 13 0 1 0 2 15c0 10.5 13 26 13 26Z"
        fill="#0a5c49" stroke="#faf7f2" stroke-width="2.5" stroke-linejoin="round"/>
  <circle cx="15" cy="15" r="4.5" fill="#faf7f2"/>
</svg>`;

type Props = {
    center: LatLng;
    zoom?: number;
    /** Lets the visitor move the pin by dragging it or tapping the map. */
    interactive?: boolean;
    onMove?: (position: LatLng) => void;
    className?: string;
    ariaLabel: string;
    /** Accessible name for the pin itself, required when interactive. */
    markerLabel?: string;
};

/**
 * A Leaflet map with a single pin.
 *
 * Leaflet and its stylesheet are imported dynamically, so roughly 45KB of map
 * code never reaches a visitor who does not open one.
 */
export function MapCanvas({
    center,
    zoom = 16,
    interactive = false,
    onMove,
    className,
    ariaLabel,
    markerLabel,
}: Props) {
    const holder = useRef<HTMLDivElement>(null);
    const map = useRef<LeafletMap | null>(null);
    const marker = useRef<Marker | null>(null);

    // Held in a ref so a new callback identity on every parent render does not
    // tear the map down and rebuild it. Assigned in an effect rather than
    // during render, which React does not allow.
    const onMoveRef = useRef(onMove);

    useEffect(() => {
        onMoveRef.current = onMove;
    });

    useEffect(() => {
        let cancelled = false;

        void (async () => {
            const L = (await import('leaflet')).default;
            await import('leaflet/dist/leaflet.css');

            // Strict mode mounts, unmounts and mounts again. Without this the
            // resolved import of the discarded pass would initialise a map on
            // a container the surviving pass also claims, and Leaflet throws.
            if (cancelled || !holder.current) {
                return;
            }

            const instance = L.map(holder.current, {
                center: [center.lat, center.lng],
                zoom,
                // A map that swallows page scroll is a trap on a phone.
                scrollWheelZoom: interactive,
                zoomControl: interactive,
                attributionControl: true,
            });

            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap',
            }).addTo(instance);

            const pin = L.marker([center.lat, center.lng], {
                draggable: interactive,
                keyboard: interactive,
                icon: L.divIcon({
                    html: PIN,
                    className: 'border-0 bg-transparent',
                    iconSize: [30, 42],
                    iconAnchor: [15, 42],
                }),
            }).addTo(instance);

            if (interactive) {
                // Leaflet gives a draggable marker role="button" and makes it
                // focusable, but names it only for image icons. Ours is a
                // divIcon, so the name has to be set here.
                pin.getElement()?.setAttribute(
                    'aria-label',
                    markerLabel ?? ariaLabel,
                );

                pin.on('dragend', () => {
                    const { lat, lng } = pin.getLatLng();
                    onMoveRef.current?.({ lat, lng });
                });

                instance.on('click', (event) => {
                    pin.setLatLng(event.latlng);
                    onMoveRef.current?.({
                        lat: event.latlng.lat,
                        lng: event.latlng.lng,
                    });
                });
            }

            map.current = instance;
            marker.current = pin;
        })();

        return () => {
            cancelled = true;
            map.current?.remove();
            map.current = null;
            marker.current = null;
        };
        // Built once. Later coordinate changes are applied below rather than
        // by tearing the map down, which would flash and refetch every tile.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        map.current?.setView([center.lat, center.lng]);
        marker.current?.setLatLng([center.lat, center.lng]);
    }, [center.lat, center.lng]);

    return (
        <div
            ref={holder}
            role="application"
            aria-label={ariaLabel}
            className={className}
        />
    );
}
