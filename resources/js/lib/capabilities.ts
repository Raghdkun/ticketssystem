/**
 * Why a device capability is or is not available here.
 *
 * The three failures below look identical to a user — the feature just does
 * not work — but only one of them is theirs to fix. Telling a venue owner
 * "camera denied" when the real problem is that the page is not on HTTPS
 * sends them into their browser settings to change something that was never
 * set, which is the single most common "it works on my laptop but not on my
 * phone" report.
 */
export type Capability = 'camera' | 'geolocation' | 'notifications';

export type CapabilityStatus =
    /** Available. It may still prompt the first time. */
    | 'ready'
    /** Already granted. */
    | 'granted'
    /** The user said no. Recoverable, but only from browser settings. */
    | 'denied'
    /** Not HTTPS. Nothing the user can do; the deploy has to be fixed. */
    | 'insecure'
    /** This browser has no such API. */
    | 'unsupported'
    /** Our own Permissions-Policy header forbids it on this route. */
    | 'blocked';

/**
 * Powerful features are gated on a secure context. Browsers treat localhost
 * as secure, so this only ever fires on a real host served over plain HTTP —
 * which is exactly what a phone on the LAN hits.
 */
function secure(): boolean {
    return typeof window !== 'undefined' && window.isSecureContext;
}

/**
 * What the browser says about a permission it has already decided.
 *
 * This is the authority. `document.featurePolicy` was measured reporting
 * `false` for the camera on a page whose header said `camera=(self)` — it is
 * a deprecated API and it conflates a user's refusal with a site policy, so
 * asking it first made the app tell owners the site was blocking a camera
 * they had simply declined. Which is exactly backwards.
 */
async function decided(feature: Capability): Promise<PermissionState | null> {
    if (feature === 'notifications') {
        return Notification.permission === 'default'
            ? 'prompt'
            : (Notification.permission as PermissionState);
    }

    try {
        const status = await navigator.permissions.query({
            name: feature as PermissionName,
        });

        return status.state;
    } catch {
        // Firefox has no 'camera' descriptor, and Safari's support is
        // partial. No answer is not the same as a refusal.
        return null;
    }
}

/**
 * Whether our own Permissions-Policy header allows the feature here.
 *
 * Only consulted when the browser has not already decided, because it cannot
 * tell the two apart on its own.
 */
function allowedByPolicy(feature: Capability): boolean {
    if (feature === 'notifications') {
        // Notifications are not a Permissions-Policy feature in any shipping
        // browser, so the header cannot be the reason they fail.
        return true;
    }

    const policy = (
        document as Document & {
            featurePolicy?: { allowsFeature(name: string): boolean };
        }
    ).featurePolicy;

    // No API to ask with is not evidence of a block.
    return policy ? policy.allowsFeature(feature) : true;
}

export async function capabilityStatus(
    feature: Capability,
): Promise<CapabilityStatus> {
    if (typeof window === 'undefined') {
        return 'unsupported';
    }

    const present =
        feature === 'camera'
            ? Boolean(navigator.mediaDevices?.getUserMedia)
            : feature === 'geolocation'
              ? 'geolocation' in navigator
              : 'Notification' in window;

    // Order matters: an insecure context is why the API is missing, so report
    // the cause rather than the symptom.
    if (!secure()) {
        return 'insecure';
    }

    if (!present) {
        return 'unsupported';
    }

    // The browser's own answer beats anything we can infer.
    const state = await decided(feature);

    if (state === 'denied') {
        return 'denied';
    }

    if (state === 'granted') {
        return 'granted';
    }

    // Only now is the header worth asking about: nothing has been decided, so
    // a refusal here really is our own policy.
    if (!allowedByPolicy(feature)) {
        return 'blocked';
    }

    return 'ready';
}

/**
 * Turns a getUserMedia or Geolocation failure into a status.
 *
 * Both APIs report a refusal and a policy block with the same error name, so
 * the policy is checked first rather than inferred from the error.
 */
export function statusFromError(
    feature: Capability,
    error: unknown,
): CapabilityStatus {
    if (!secure()) {
        return 'insecure';
    }

    const name =
        error instanceof Error
            ? error.name
            : typeof error === 'object' && error !== null && 'code' in error
              ? // GeolocationPositionError has a numeric code, not a name.
                { 1: 'NotAllowedError', 2: 'NotFoundError', 3: 'TimeoutError' }[
                    (error as GeolocationPositionError).code
                ]
              : undefined;

    switch (name) {
        case 'NotAllowedError':
        case 'SecurityError':
            return 'denied';
        case 'NotFoundError':
        case 'OverconstrainedError':
            return 'unsupported';
        default:
            return 'denied';
    }
}

/** Translation key describing a status, for the given feature. */
export function capabilityMessage(
    feature: Capability,
    status: CapabilityStatus,
): string {
    if (status === 'insecure') {
        return 'perm.insecure';
    }

    if (status === 'blocked') {
        return 'perm.blocked';
    }

    if (status === 'unsupported') {
        return `perm.${feature}_unsupported`;
    }

    return `perm.${feature}_denied`;
}
