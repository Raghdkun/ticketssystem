import { Download, QrCode } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/lib/translation';

/**
 * The event's own QR, for posters and flyers.
 *
 * Deliberately not the ticket QR: this one encodes the public event page, so
 * scanning it starts a booking. A ticket QR encodes an auth-gated verification
 * URL and must never be printed in bulk.
 */
export function EventQrCard({ eventId }: { eventId: number }) {
    const t = useTranslation();
    const href = `/owner/events/${eventId}/qr.png`;

    return (
        <section className="brand-surface flex flex-wrap items-center gap-4 rounded-xl border p-4 sm:p-6">
            <div className="flex size-16 shrink-0 items-center justify-center rounded-xl bg-muted">
                <QrCode className="size-8 text-muted-foreground" aria-hidden />
            </div>

            <div className="min-w-0 flex-1">
                <h2 className="text-sm font-medium">{t('owner.qr_title')}</h2>
                <p className="mt-1 text-xs text-muted-foreground">
                    {t('owner.qr_hint')}
                </p>
            </div>

            {/* A plain anchor, not an Inertia link: this is a file download,
                and a client-side visit would try to render a PNG as a page. */}
            <Button asChild variant="outline" className="w-full sm:w-auto">
                <a href={href} download>
                    <Download />
                    {t('owner.qr_download')}
                </a>
            </Button>
        </section>
    );
}
