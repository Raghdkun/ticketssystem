import { createInertiaApp } from '@inertiajs/react';
import { PageTransition } from '@/components/page-transition';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { bootEcho } from '@/lib/echo';
import { registerServiceWorker } from '@/lib/pwa';
bootEcho();
registerServiceWorker();

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
                return null;
            // Public pages are standalone: no sidebar, no authenticated chrome.
            case name.startsWith('public/'):
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                <PageTransition>{app}</PageTransition>
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        // Jade, not the starter kit's grey. This is the only feedback a slow
        // visit gives, so it should look like the product.
        color: '#12876A',
        delay: 120,
    },
});

// This will set light / dark mode on load...
initializeTheme();
