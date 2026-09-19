import { usePage } from '@inertiajs/react';
import { Disc } from '@/components/brand/disc';
import { Wordmark } from '@/components/brand/wordmark';

/**
 * The brand in the sidebar header.
 *
 * The wordmark is the name, so no text sits beside it. When the sidebar
 * collapses to its icon rail there is no room for three Arabic letters and the
 * disc takes over; the platform name stays for assistive technology in both
 * states.
 */
export default function AppLogo() {
    const { name } = usePage<{ name: string }>().props;

    return (
        <>
            <Wordmark
                decorative
                className="h-9 text-foreground group-data-[collapsible=icon]:hidden"
            />
            <Disc className="hidden size-8 group-data-[collapsible=icon]:block" />
            <span className="sr-only">{name}</span>
        </>
    );
}
