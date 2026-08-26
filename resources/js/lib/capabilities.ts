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
 * Whether our own Permissions-Policy header allows the feature here.
 *
 * A feature denied by that header cannot be granted by prompting, so it must
 * be reported as our bug rather than as a refusal.
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

export function capabilityStatus(feature: Capability): CapabilityStatus {
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

    if (!allowedByPolicy(feature)) {
        return 'blocked';
    }

    if (feature === 'notifications') {
        if (Notification.permission === 'denied') {
            return 'denied';
        }

        if (Notification.permission === 'granted') {
            return 'granted';
        }
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
    if (!allowedByPolicy(feature)) {
        return 'blocked';
    }

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
