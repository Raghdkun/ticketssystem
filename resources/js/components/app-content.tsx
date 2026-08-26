import * as React from 'react';
import { SidebarInset } from '@/components/ui/sidebar';
import type { AppVariant } from '@/types';

type Props = React.ComponentProps<'main'> & {
    variant?: AppVariant;
};

export function AppContent({ variant = 'sidebar', children, ...props }: Props) {
    // SidebarInset renders the <main> for the whole authenticated side of the
    // app, so the skip link's target has to live here too. Without it the
    // link pointed at nothing on every owner and admin page.
    if (variant === 'sidebar') {
        return (
            <SidebarInset id="main-content" {...props}>
                {children}
            </SidebarInset>
        );
    }

    return (
        <main
            id="main-content"
            className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-4 rounded-xl"
            {...props}
        >
            {children}
        </main>
    );
}
