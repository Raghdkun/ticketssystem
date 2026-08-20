/**
 * Web push opt-in.
 *
 * Every entry point is guarded so the app behaves identically when Firebase is
 * not configured: no permission prompt, no console noise, no broken UI. The
 * Firebase SDK is imported lazily so its weight only lands for users who
 * actually opt in.
 */

export type PushSupport =
    | 'ready'
    | 'unconfigured'
    | 'unsupported'
    | 'needs-install'
    | 'denied'
    | 'granted';

function configured(): boolean {
    return Boolean(
        import.meta.env.VITE_FCM_API_KEY && import.meta.env.VITE_FCM_VAPID_KEY,
    );
}

function isIos(): boolean {
    return /iphone|ipad|ipod/i.test(navigator.userAgent);
}

function isStandalone(): boolean {
    return (
        window.matchMedia('(display-mode: standalone)').matches ||
        (window.navigator as { standalone?: boolean }).standalone === true
    );
}

/**
 * Why push is or is not available here. iOS only exposes the Push API to
 * home-screen web apps, so an iPhone user in Safari gets `needs-install`
 * rather than a prompt that cannot succeed.
 */
export function pushSupport(): PushSupport {
    if (!configured()) {
        return 'unconfigured';
    }

    if (!('Notification' in window) || !('serviceWorker' in navigator)) {
        return isIos() && !isStandalone() ? 'needs-install' : 'unsupported';
    }

    if (isIos() && !isStandalone()) {
        return 'needs-install';
    }

    if (Notification.permission === 'denied') {
        return 'denied';
    }

    if (Notification.permission === 'granted') {
        return 'granted';
    }

    return 'ready';
}

/**
 * Request permission and register this device against a ticket.
 *
 * @returns true when the device is now subscribed.
 */
export async function subscribeToTicket(token: string): Promise<boolean> {
    if (!configured()) {
        return false;
    }

    try {
        const permission = await Notification.requestPermission();

        if (permission !== 'granted') {
            return false;
        }

        const { initializeApp } = await import('firebase/app');
        const { getMessaging, getToken } = await import('firebase/messaging');

        const app = initializeApp({
            apiKey: import.meta.env.VITE_FCM_API_KEY,
            authDomain: import.meta.env.VITE_FCM_AUTH_DOMAIN,
            projectId: import.meta.env.VITE_FCM_PROJECT_ID,
            storageBucket: import.meta.env.VITE_FCM_STORAGE_BUCKET,
            messagingSenderId: import.meta.env.VITE_FCM_SENDER_ID,
            appId: import.meta.env.VITE_FCM_APP_ID,
        });

        // Register the Firebase worker explicitly. `navigator.serviceWorker
        // .ready` resolves to whichever worker controls the page — that is the
        // app's own sw.js, which has no Firebase message handler, so passing it
        // would register a token that never delivers a background notification.
        const registration = await navigator.serviceWorker.register(
            '/firebase-messaging-sw.js',
            { scope: '/firebase-cloud-messaging-push-scope' },
        );
        const fcmToken = await getToken(getMessaging(app), {
            vapidKey: import.meta.env.VITE_FCM_VAPID_KEY,
            serviceWorkerRegistration: registration,
        });

        if (!fcmToken) {
            return false;
        }

        const csrf =
            document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
                ?.content ?? '';

        const response = await fetch(`/t/${token}/push`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({ token: fcmToken }),
        });

        return response.ok;
    } catch {
        // Blocked, offline, or an unavailable messaging service: the ticket
        // page keeps working over Reverb regardless.
        return false;
    }
}
