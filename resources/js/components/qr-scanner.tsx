import { Scanner } from '@yudiel/react-qr-scanner';
import type { IDetectedBarcode } from '@yudiel/react-qr-scanner';
import { Camera, CameraOff } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    capabilityMessage,
    capabilityStatus,
    statusFromError,
} from '@/lib/capabilities';
import type { CapabilityStatus } from '@/lib/capabilities';
import { useTranslation } from '@/lib/translation';

type Props = {
    /** Called with the ticket token decoded from a scanned QR. */
    onToken: (token: string) => void;
};

/**
 * Extracts the ticket token from a scanned value.
 *
 * Tickets encode a full verification URL, but accept a bare token too so a
 * hand-typed reference still works. Returns null for anything else, so
 * scanning an unrelated QR is ignored rather than producing a bad lookup.
 */
export function tokenFromScan(value: string): string | null {
    const fromUrl = value.match(/\/verify\/([a-z0-9]{32})/i);

    if (fromUrl) {
        return fromUrl[1].toLowerCase();
    }

    return /^[a-z0-9]{32}$/i.test(value.trim())
        ? value.trim().toLowerCase()
        : null;
}

export function QrScanner({ onToken }: Props) {
    const t = useTranslation();
    const [active, setActive] = useState(false);
    const [error, setError] = useState<string | null>(null);

    // Checked on mount, not on tap: if the page is not on HTTPS there is
    // nothing to try, and offering a button that cannot work is a worse
    // answer than saying so.
    // Checked on mount, not on tap: if the page is not on HTTPS there is
    // nothing to try, and offering a button that cannot work is a worse
    // answer than saying so. Asynchronous because the browser's own verdict
    // on the permission is the only trustworthy source.
    const [status, setStatus] = useState<CapabilityStatus | null>(null);

    useEffect(() => {
        let cancelled = false;

        void capabilityStatus('camera').then((result) => {
            if (!cancelled) {
                setStatus(result);
            }
        });

        return () => {
            cancelled = true;
        };
    }, []);

    const unavailable =
        status === 'insecure' ||
        status === 'blocked' ||
        status === 'unsupported';

    const handleScan = (codes: IDetectedBarcode[]) => {
        for (const code of codes) {
            const token = tokenFromScan(code.rawValue);

            if (token) {
                setActive(false);
                onToken(token);

                return;
            }
        }
    };

    return (
        <div className="space-y-3">
            {active ? (
                <div className="overflow-hidden rounded-xl border">
                    <Scanner
                        onScan={handleScan}
                        onError={(cause) =>
                            setError(
                                t(
                                    capabilityMessage(
                                        'camera',
                                        statusFromError('camera', cause),
                                    ),
                                ),
                            )
                        }
                        constraints={{ facingMode: 'environment' }}
                        // The library prefers the native BarcodeDetector where
                        // the browser provides it, and falls back to WASM.
                        formats={['qr_code']}
                        allowMultiple={false}
                    />
                </div>
            ) : null}

            <Button
                type="button"
                variant={active ? 'outline' : 'default'}
                className="w-full"
                disabled={unavailable}
                onClick={() => {
                    setError(null);
                    setActive((value) => !value);
                }}
            >
                {active ? <CameraOff /> : <Camera />}
                {active ? t('owner.scan_stop') : t('owner.scan_start')}
            </Button>

            {unavailable && status !== null && !error && (
                <p className="rounded-lg bg-amber-50 p-3 text-center text-xs text-amber-900 dark:bg-amber-950/50 dark:text-amber-200">
                    {t(capabilityMessage('camera', status))}
                </p>
            )}

            {error && (
                <p className="rounded-lg bg-amber-50 p-3 text-center text-xs text-amber-900 dark:bg-amber-950/50 dark:text-amber-200">
                    {error}
                </p>
            )}
        </div>
    );
}
