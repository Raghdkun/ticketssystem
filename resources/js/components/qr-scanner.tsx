import { Scanner } from '@yudiel/react-qr-scanner';
import type { IDetectedBarcode } from '@yudiel/react-qr-scanner';
import { Camera, CameraOff } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
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
                        onError={() => setError(t('owner.scan_denied'))}
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
                onClick={() => {
                    setError(null);
                    setActive((value) => !value);
                }}
            >
                {active ? <CameraOff /> : <Camera />}
                {active ? t('owner.scan_stop') : t('owner.scan_start')}
            </Button>

            {error && (
                <p className="rounded-lg bg-amber-50 p-3 text-center text-xs text-amber-900 dark:bg-amber-950/50 dark:text-amber-200">
                    {error}
                </p>
            )}
        </div>
    );
}
