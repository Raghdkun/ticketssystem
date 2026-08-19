import type { SVGAttributes } from 'react';

/**
 * Swaida Tickets Hub mark.
 *
 * A ticket stub whose perforation doubles as the spokes of a hub: the two
 * ideas the product joins. Drawn on a 40×40 grid with a single path so it
 * stays legible at favicon size, and uses currentColor so it inherits the
 * surrounding text colour in both themes.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 40 40"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
        >
            {/* Ticket body with notched sides */}
            <path
                d="M5 11a3 3 0 0 1 3-3h24a3 3 0 0 1 3 3v3.6a.9.9 0 0 1-.72.88 4.6 4.6 0 0 0 0 9.04.9.9 0 0 1 .72.88V29a3 3 0 0 1-3 3H8a3 3 0 0 1-3-3v-3.6a.9.9 0 0 1 .72-.88 4.6 4.6 0 0 0 0-9.04A.9.9 0 0 1 5 14.6V11Z"
                fill="currentColor"
                fillOpacity="0.14"
                stroke="currentColor"
                strokeWidth="2.2"
                strokeLinejoin="round"
            />

            {/* Perforation, reading as hub spokes */}
            <path
                d="M20 12.5v3M20 18.5v3M20 24.5v3"
                stroke="currentColor"
                strokeWidth="2.2"
                strokeLinecap="round"
            />
        </svg>
    );
}
