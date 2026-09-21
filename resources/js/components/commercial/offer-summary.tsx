import { Badge } from '@/components/ui/badge';
import { dateTag, formatMoney } from '@/lib/format';
import { localised, useLocale } from '@/lib/locale';
import { useTranslation } from '@/lib/translation';

export type Signature = {
    name: string | null;
    role: string | null;
    phone: string | null;
    method: string | null;
    ip: string | null;
    by: string | null;
} | null;

export type Offer = {
    id: number;
    status:
        'draft' | 'sent' | 'accepted' | 'rejected' | 'expired' | 'superseded';
    title_ar: string;
    title_en: string;
    fee_type: string;
    fee_value: number | null;
    fee_payer: string;
    settlement_days: number | null;
    subscription_amount: number | null;
    currency: string | null;
    included_services_ar: string | null;
    included_services_en: string | null;
    additional_terms_ar: string | null;
    additional_terms_en: string | null;
    valid_from: string | null;
    valid_until: string | null;
    content_hash: string | null;
    sent_at: string | null;
    accepted_at: string | null;
    rejected_at: string | null;
    superseded_at: string | null;
    signature: Signature;
    place: { slug: string; name_ar: string; name_en: string };
};

const BADGE: Record<
    Offer['status'],
    'default' | 'secondary' | 'outline' | 'destructive'
> = {
    accepted: 'default',
    sent: 'secondary',
    draft: 'outline',
    rejected: 'destructive',
    expired: 'outline',
    superseded: 'outline',
};

export function OfferStatusBadge({ status }: { status: Offer['status'] }) {
    const t = useTranslation();

    return (
        <Badge variant={BADGE[status]}>
            {t(`commercial.status.${status}`)}
        </Badge>
    );
}

/**
 * The commercial terms as a plain table: what the fee is, who pays it,
 * when it settles, what is included. The same card on the venue's screen
 * and the administrator's, so both read the same thing.
 */
export function OfferSummary({ offer }: { offer: Offer }) {
    const t = useTranslation();
    const { locale } = useLocale();
    const dateLocale = dateTag(locale);

    const fee =
        offer.fee_type === 'percentage' && offer.fee_value !== null
            ? `${offer.fee_value}%`
            : offer.fee_value !== null
              ? formatMoney(offer.fee_value, offer.currency ?? '')
              : t(`commercial.fee_types.${offer.fee_type}`);

    const date = (iso: string) =>
        new Date(iso).toLocaleDateString(dateLocale, { dateStyle: 'medium' });

    const validity =
        offer.valid_from && offer.valid_until
            ? t('commercial.valid_between', {
                  from: date(offer.valid_from),
                  until: date(offer.valid_until),
              })
            : offer.valid_from
              ? t('commercial.valid_from', { from: date(offer.valid_from) })
              : offer.valid_until
                ? t('commercial.valid_until', {
                      until: date(offer.valid_until),
                  })
                : null;

    const included = localised(
        locale,
        offer.included_services_ar,
        offer.included_services_en,
    );
    const additional = localised(
        locale,
        offer.additional_terms_ar,
        offer.additional_terms_en,
    );

    return (
        <dl className="grid gap-4 rounded-xl border bg-card p-5 text-sm sm:grid-cols-2">
            <Row label={t('commercial.fee')} value={fee} strong />
            <Row
                label={t('commercial.fee_payer')}
                value={t(`commercial.fee_payers.${offer.fee_payer}`)}
            />
            <Row
                label={t('commercial.settlement')}
                value={
                    offer.settlement_days === null
                        ? t('commercial.settlement_none')
                        : t('commercial.settlement_days', {
                              n: offer.settlement_days,
                          })
                }
            />
            {offer.subscription_amount !== null && (
                <Row
                    label={t('commercial.subscription')}
                    value={formatMoney(
                        offer.subscription_amount,
                        offer.currency ?? '',
                    )}
                />
            )}
            {validity && (
                <Row label={t('commercial.validity')} value={validity} />
            )}
            {included && (
                <Row label={t('commercial.included')} value={included} wide />
            )}
            {additional && (
                <Row
                    label={t('commercial.additional')}
                    value={additional}
                    wide
                />
            )}
        </dl>
    );
}

function Row({
    label,
    value,
    strong,
    wide,
}: {
    label: string;
    value: string;
    strong?: boolean;
    wide?: boolean;
}) {
    return (
        <div className={wide ? 'sm:col-span-2' : undefined}>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd
                className={
                    strong
                        ? 'text-xl font-extrabold text-primary-text tabular-nums'
                        : 'font-medium whitespace-pre-line'
                }
            >
                {value}
            </dd>
        </div>
    );
}

/**
 * Who signed, from where, and the fingerprint of what they signed.
 */
export function SignatureRecord({
    signature,
    acceptedAt,
    hash,
    title,
}: {
    signature: Signature;
    acceptedAt: string | null;
    hash: string | null;
    title: string;
}) {
    const t = useTranslation();
    const { locale } = useLocale();

    if (!signature || !acceptedAt) {
        return null;
    }

    return (
        <section className="space-y-3 rounded-xl border p-5">
            <h2 className="text-sm font-extrabold">{title}</h2>
            <dl className="grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt className="text-xs text-muted-foreground">
                        {t('documents.accepted_on', {
                            date: new Date(acceptedAt).toLocaleString(
                                dateTag(locale),
                                { dateStyle: 'medium', timeStyle: 'short' },
                            ),
                        })}
                    </dt>
                    <dd className="font-medium">
                        {signature.name}
                        {signature.role &&
                            ` · ${t(`agreement.roles.${signature.role}`)}`}
                    </dd>
                </div>
                {signature.phone && (
                    <div>
                        <dt className="text-xs text-muted-foreground">
                            {t('agreement.representative_phone')}
                        </dt>
                        <dd dir="ltr" className="text-start font-medium">
                            {signature.phone}
                        </dd>
                    </div>
                )}
                {signature.method && (
                    <div>
                        <dt className="text-xs text-muted-foreground">
                            {t('agreement.method_label')}
                        </dt>
                        <dd className="font-medium">
                            {t(`agreement.method.${signature.method}`)}
                        </dd>
                    </div>
                )}
                {hash && (
                    <div>
                        <dt className="text-xs text-muted-foreground">
                            {t('commercial.hash')}
                        </dt>
                        <dd dir="ltr" className="text-start font-mono text-xs">
                            {hash.slice(0, 16)}
                        </dd>
                    </div>
                )}
            </dl>
        </section>
    );
}
