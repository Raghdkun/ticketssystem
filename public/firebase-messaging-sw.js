/*
 * Firebase background messaging worker.
 *
 * This file must live at the origin root and be plain, un-bundled JavaScript:
 * the browser fetches it directly, so it cannot import from the app bundle or
 * read Vite env vars. The config below is therefore inlined.
 *
 * These values are safe to publish. A Firebase web apiKey identifies the
 * project, it does not authorise anything on its own — access is governed by
 * Firebase security rules and, for sending, by the service-account key that
 * stays on the server and is never shipped to a browser.
 */
importScripts('https://www.gstatic.com/firebasejs/10.12.2/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.12.2/firebase-messaging-compat.js');

firebase.initializeApp({
    apiKey: 'AIzaSyAkNjv4E5Mnra1AP-v2YPrwbrAF-dAYYHY',
    authDomain: 'swaida-tickets.firebaseapp.com',
    projectId: 'swaida-tickets',
    storageBucket: 'swaida-tickets.firebasestorage.app',
    messagingSenderId: '944064210595',
    appId: '1:944064210595:web:d2a1929a6e105e73f14116',
});

const messaging = firebase.messaging();

/*
 * Fires only when the page is not in the foreground. A ticket holder who is
 * looking at their ticket already gets the live flip over Reverb, so showing a
 * system notification as well would be duplicate noise.
 */
messaging.onBackgroundMessage((payload) => {
    const title = payload.notification?.title ?? 'Swaida Tickets Hub';
    const body = payload.notification?.body ?? '';
    const link = payload.fcmOptions?.link ?? payload.data?.link ?? '/';

    self.registration.showNotification(title, {
        body,
        icon: '/icons/icon-192.png',
        badge: '/icons/icon-192.png',
        // Collapse repeats for the same ticket rather than stacking them.
        tag: payload.data?.ticket ?? 'ticket-status',
        renotify: true,
        data: { link },
    });
});

/* Tapping the notification focuses an open tab for that ticket, or opens one. */
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const link = event.notification.data?.link ?? '/';

    event.waitUntil(
        self.clients
            .matchAll({ type: 'window', includeUncontrolled: true })
            .then((clients) => {
                for (const client of clients) {
                    if (client.url.includes(link) && 'focus' in client) {
                        return client.focus();
                    }
                }

                return self.clients.openWindow(link);
            }),
    );
});
