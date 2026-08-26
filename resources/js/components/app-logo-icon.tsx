import type { SVGAttributes } from 'react';
import { useLocale } from '@/lib/locale';

/**
 * The Pass — the Swaida Tickets Hub mark.
 *
 * A solid die-cut ticket. There is no letterform in it, so nothing needs
 * translating: any script sits beside the mark and can be swapped without
 * touching the drawing. Three moves only — the notch, the tear, the dot that
 * is the person who got in.
 *
 * The body inherits `currentColor`, so the mark takes the colour of whatever
 * it sits in. The punched-out parts follow `--mark-cut`, which defaults to the
 * page surface — a punch reveals what is behind it, so on the dark theme the
 * holes have to go dark too or they stop reading as holes. Override it
 * wherever the mark is reversed onto a solid tile. Only the saffron dot is
 * fixed, because it is the one accent.
 */
export default function AppLogoIcon({
    /**
     * Detail is dropped at small sizes on purpose: the artboard's rule is that
     * dots disappear below 48px, and a perforation rendered into 16px is mush.
     */
    detail = 'full',
    ...props
}: SVGAttributes<SVGElement> & { detail?: 'full' | 'compact' | 'minimal' }) {
    const { direction } = useLocale();

    return (
        <svg
            {...props}
            viewBox="0 0 48 48"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            // Mirrored in Arabic so the stub always trails the reading
            // direction — the tear happens "after" the name in both scripts.
            style={{
                ...props.style,
                transform:
                    direction === 'rtl' ? 'scaleX(-1)' : props.style?.transform,
            }}
        >
            <g data-mark="body">
                <path
                    d="M12 10h14.5a3.5 3.5 0 0 0 7 0H36a6 6 0 0 1 6 6v16a6 6 0 0 1-6 6h-2.5a3.5 3.5 0 0 0-7 0H12a6 6 0 0 1-6-6V16a6 6 0 0 1 6-6Z"
                    fill="currentColor"
                />

                {detail === 'full' && (
                    <path
                        d="M30 16v17"
                        stroke="var(--mark-cut, var(--background))"
                        strokeWidth="2.6"
                        strokeLinecap="round"
                        strokeDasharray="0 5.4"
                        data-mark="tear"
                    />
                )}

                <circle
                    cx="18.5"
                    cy="24"
                    r={detail === 'minimal' ? 6 : 4.6}
                    fill="var(--mark-cut, var(--background))"
                />

                {detail !== 'minimal' && (
                    <circle
                        cx="36"
                        cy="24"
                        r="3.2"
                        fill="#E8A72B"
                        data-mark="admit"
                    />
                )}
            </g>
        </svg>
    );
}
