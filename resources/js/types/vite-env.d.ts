/// <reference types="vite/client" />

interface ImportMetaEnv {
    readonly VITE_APP_NAME: string;

    readonly VITE_REVERB_APP_KEY: string;
    readonly VITE_REVERB_HOST: string;
    readonly VITE_REVERB_PORT: string;
    readonly VITE_REVERB_SCHEME: string;

    /*
     * Firebase web config. These ship in the client bundle by design: the
     * apiKey identifies the project rather than authorising anything. The
     * service-account key used to SEND messages stays on the server.
     */
    readonly VITE_FCM_API_KEY: string;
    readonly VITE_FCM_AUTH_DOMAIN: string;
    readonly VITE_FCM_PROJECT_ID: string;
    readonly VITE_FCM_STORAGE_BUCKET: string;
    readonly VITE_FCM_SENDER_ID: string;
    readonly VITE_FCM_APP_ID: string;
    readonly VITE_FCM_MEASUREMENT_ID: string;
    /** Web Push certificate. Without it getToken() cannot mint a token. */
    readonly VITE_FCM_VAPID_KEY: string;
}

interface ImportMeta {
    readonly env: ImportMetaEnv;
}
