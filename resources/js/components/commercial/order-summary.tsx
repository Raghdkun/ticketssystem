import { Badge } from '@/components/ui/badge';
import { formatMoney } from '@/lib/format';
import { localised, useLocale } from '@/lib/locale';
import { useTranslation } from '@/lib/translation';
import type { Signature } from './offer-summary';

export type Order = {
    id: number;
    status:
        | 'draft'
        | 'sent'
        | 'accepted'
        | 'in_progress'
        | 'completed'
        | 'cancelled';
    service_type: string;
    title_ar: string;
    title_en: string;
    description_ar: string | null;
    description_en: string | null;
    quantity: number;
    unit_price: number | null;
    total: number | null;
    currency: string | null;
    terms_ar: string | null;
    terms_en: string | null;
    content_hash: string | null;
    sent_at: string | null;
    accepted_at: string | null;
    completed_at: string | null;
    cancelled_at: string | null;
    signature: Signature;
    event: { id: number; title_ar: string; title_en: string } | null;
    place: { slug: string; name_ar: string; name_en: string };
};

const BADGE: Record<
    Order['status'],
    'default' | 'secondary' | 'outline' | 'destructive'
> = {
    accepted: 'default',
    in_progress: 'default',
    completed: 'outline',
    sent: 'secondary',
    draft: 'outline',
    cancelled: 'destructive',
};

export function OrderStatusBadge({ status }: { status: Order['status'] }) {
    const t = useTranslation();

    return (
        <Badge variant={BADGE[status]}>{t(`orders.status.${status}`)}</Badge>
    );
}

/**
 * One service, as a card: what, how many, at what price, under what terms.
 */
export function OrderSummary({ order }: { order: Order }) {
    const t = useTranslation();
    const { locale } = useLocale();

    const description = localised(
        locale,
        order.description_ar,
        order.description_en,
    );
    const terms = localised(locale, order.terms_ar, order.terms_en);

    return (
        <dl className="grid gap-4 rounded-xl border bg-card p-5 text-sm sm:grid-cols-2">
            <div>
                <dt className="text-xs text-muted-foreground">
                    {t('orders.type')}
                </dt>
                <dd className="font-medium">
                    {t(`orders.types.${order.service_type}`)}
                </dd>
            </div>
            {order.event && (
                <div>
                    <dt className="text-xs text-muted-foreground">
                        {t('orders.for_event')}
                    </dt>
                    <dd className="font-medium">
                        {localised(
                            locale,
                            order.event.title_ar,
                            order.event.title_en,
                        )}
                    </dd>
                </div>
            )}
            {description && (
                <div className="sm:col-span-2">
                    <dt className="text-xs text-muted-foreground">
                        {t('orders.details')}
                    </dt>
                    <dd className="font-medium whitespace-pre-line">
                        {description}
                    </dd>
                </div>
            )}
            <div>
                <dt className="text-xs text-muted-foreground">
                    {t('orders.quantity')}
                </dt>
                <dd className="font-medium tabular-nums">{order.quantity}</dd>
            </div>
            {order.unit_price !== null && (
                <div>
                    <dt className="text-xs text-muted-foreground">
                        {t('orders.unit_price')}
                    </dt>
                    <dd className="font-medium tabular-nums">
                        {formatMoney(order.unit_price, order.currency ?? '')}
                    </dd>
                </div>
            )}
            {order.total !== null && (
                <div className="sm:col-span-2">
                    <dt className="text-xs text-muted-foreground">
                        {t('orders.total')}
                    </dt>
                    <dd className="text-xl font-extrabold text-primary-text tabular-nums">
                        {formatMoney(order.total, order.currency ?? '')}
                    </dd>
                </div>
            )}
            {terms && (
                <div className="sm:col-span-2">
                    <dt className="text-xs text-muted-foreground">
                        {t('orders.terms')}
                    </dt>
                    <dd className="font-medium whitespace-pre-line">{terms}</dd>
                </div>
            )}
        </dl>
    );
}
