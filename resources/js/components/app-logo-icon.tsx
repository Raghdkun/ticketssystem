import type { SVGAttributes } from 'react';

/**
 * App mark: a ticket stub with a perforation and punch-outs, matching the
 * shape of the digital ticket the product is built around.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
            <path
                fillRule="evenodd"
                clipRule="evenodd"
                d="M4 8a3 3 0 0 1 3-3h26a3 3 0 0 1 3 3v5.2a1 1 0 0 1-.8.98 4 4 0 0 0 0 7.84 1 1 0 0 1 .8.98V32a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3v-9a1 1 0 0 1 .8-.98 4 4 0 0 0 0-7.84A1 1 0 0 1 4 13.2V8Zm22.5 3a1.25 1.25 0 0 0 0 2.5h.02a1.25 1.25 0 0 0 0-2.5H26.5Zm0 7.75a1.25 1.25 0 1 0 0 2.5h.02a1.25 1.25 0 1 0 0-2.5H26.5Zm0 7.75a1.25 1.25 0 1 0 0 2.5h.02a1.25 1.25 0 1 0 0-2.5H26.5Z"
            />
        </svg>
    );
}
