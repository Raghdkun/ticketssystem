import { Head, Link } from '@inertiajs/react';
import {
    CheckCircle2,
    Clock,
    FileSignature,
    Handshake,
    PackageCheck,
} from 'lucide-react';
import type { Offer } from '@/components/commercial/offer-summary';
import { OfferStatusBadge } from '@/components/commercial/offer-summary';
import type { Order } from '@/components/commercial/order-summary';
import { OrderStatusBadge } from '@/components/commercial/order-summary';
import { EmptyState } from '@/components/empty-state';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { dateTag } from '@/lib/format';
import { localised, useLocale } from '@/lib/locale';
import { useTranslation } from '@/lib/translation';

type Props = {
    /** Null for an account with no venue: an admin, or an owner not yet linked. */
    place: { name_ar: string; name_en: string } | null;
    terms: {
        current: {
            version: string;
            title_ar: string;
            title_en: string;
            published_at: string | null;
        } | null;
        accepted: {
            accepted_at: string;
            representative_name: string;
            representative_role: string;
        } | null;
        needs_acceptance: boolean;
    };
    offer: { current: Offer | null; pending: Offer[] };
    orders: Order[];
    history: {
        kind: 'terms' | 'offer' | 'order';
        title_ar: string;
        title_en: string;
        reference: string;
        at: string;
        representative: string | null;
        method: string | null;
        hash: string | null;
        href: string;
    }[];
};

/**
 * Agreements and documents: what this venue has signed, in four parts.
 *
 * A record, not a form. Anything that still needs a decision links to its
 * own page; nothing here can alter what was already accepted.
 */
export default function OwnerDocuments({
    place,
    terms,
    offer,
    orders,
    history,
}: Props) {
    const t = useTranslation();
    const { locale } = useLocale();
    const dateLocale = dateTag(locale);
    const date = (iso: string) =>
        new Date(iso).toLocaleDateString(dateLocale, { dateStyle: 'medium' });

    if (!place) {
        return (
            <>
                <Head title={t('documents.title')} />
                <div className="p-4">
                    <EmptyState
                        icon={FileSignature}
                        title={t('dash.no_place')}
                    />
                </div>
            </>
        );
    }

    return (
        <>
            <Head title={t('documents.title')} />

            <div className="space-y-6 p-4">
                <Heading
                    variant="small"
                    title={t('documents.title')}
                    description={t('documents.subtitle')}
                />

                <div className="grid gap-4 lg:grid-cols-2">
                    {/* 1. The Partner Terms */}
                    <Section icon={FileSignature} title={t('documents.terms')}>
                        {terms.current === null ? (
                            <p className="text-sm text-muted-foreground">
                                {t('documents.terms_none')}
                            </p>
                        ) : (
                            <>
                                <p className="font-semibold">
                                    {localised(
                                        locale,
                                        terms.current.title_ar,
                                        terms.current.title_en,
                                    )}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {t('agreement.version', {
                                        version: terms.current.version,
                                    })}
                                </p>
                                {terms.accepted ? (
                                    <p className="flex items-center gap-1.5 text-sm">
                                        <CheckCircle2
                                            className="size-4 text-status-paid-fg"
                                            aria-hidden="true"
                                        />
                                        {t('documents.accepted_on', {
                                            date: date(
                                                terms.accepted.accepted_at,
                                            ),
                                        })}
                                        {' · '}
                                        {terms.accepted.representative_name}
                                    </p>
                                ) : terms.needs_acceptance ? (
                                    <p className="flex items-center gap-1.5 text-sm text-status-pending-fg">
                                        <Clock
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        {t('documents.terms_pending')}
                                    </p>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        {t('documents.terms_standing')}
                                    </p>
                                )}
                                <Button
                                    asChild
                                    variant={
                                        terms.needs_acceptance
                                            ? 'default'
                                            : 'outline'
                                    }
                                    size="sm"
                                >
                                    <Link href="/owner/agreement">
                                        {terms.needs_acceptance
                                            ? t('documents.terms_accept')
                                            : t('documents.view')}
                                    </Link>
                                </Button>
                            </>
                        )}
                    </Section>

                    {/* 2. The commercial offer */}
                    <Section icon={Handshake} title={t('documents.offer')}>
                        {offer.current === null &&
                        offer.pending.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t('documents.offer_none')}
                            </p>
                        ) : (
                            <ul className="space-y-3">
                                {offer.pending.map((pending) => (
                                    <OfferRow
                                        key={pending.id}
                                        offer={pending}
                                        cta={t('documents.view')}
                                        highlight
                                    />
                                ))}
                                {offer.current && (
                                    <OfferRow
                                        offer={offer.current}
                                        cta={t('documents.view')}
                                    />
                                )}
                            </ul>
                        )}
                    </Section>
                </div>

                {/* 3. Services and orders */}
                <Section icon={PackageCheck} title={t('documents.orders')}>
                    {orders.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {t('documents.orders_none')}
                        </p>
                    ) : (
                        <ul className="divide-y">
                            {orders.map((order) => (
                                <li
                                    key={order.id}
                                    className="flex flex-wrap items-center justify-between gap-3 py-3"
                                >
                                    <div className="min-w-0">
                                        <p className="flex items-center gap-2 font-medium">
                                            {localised(
                                                locale,
                                                order.title_ar,
                                                order.title_en,
                                            )}
                                            <OrderStatusBadge
                                                status={order.status}
                                            />
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {t(
                                                `orders.types.${order.service_type}`,
                                            )}
                                            {order.event &&
                                                ` · ${localised(locale, order.event.title_ar, order.event.title_en)}`}
                                        </p>
                                    </div>
                                    <Button
                                        asChild
                                        size="sm"
                                        variant={
                                            order.status === 'sent'
                                                ? 'default'
                                                : 'outline'
                                        }
                                    >
                                        <Link
                                            href={`/owner/service-orders/${order.id}`}
                                        >
                                            {order.status === 'sent'
                                                ? t('orders.accept_button')
                                                : t('documents.view')}
                                        </Link>
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    )}
                </Section>

                {/* 4. History */}
                <section className="space-y-3">
                    <h2 className="text-sm font-extrabold">
                        {t('documents.history')}
                    </h2>
                    {history.length === 0 ? (
                        <EmptyState
                            icon={FileSignature}
                            title={t('documents.history_none')}
                        />
                    ) : (
                        <div className="overflow-x-auto rounded-xl border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-xs text-muted-foreground">
                                    <tr>
                                        <th className="p-3 text-start font-medium">
                                            {t('documents.col_document')}
                                        </th>
                                        <th className="p-3 text-start font-medium">
                                            {t('documents.col_date')}
                                        </th>
                                        <th className="p-3 text-start font-medium">
                                            {t('documents.col_representative')}
                                        </th>
                                        <th className="p-3 text-start font-medium">
                                            {t('documents.col_method')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {history.map((row) => (
                                        <tr
                                            key={`${row.kind}-${row.reference}-${row.at}`}
                                        >
                                            <td className="p-3">
                                                <Link
                                                    href={row.href}
                                                    className="font-medium underline-offset-4 hover:underline"
                                                >
                                                    {localised(
                                                        locale,
                                                        row.title_ar,
                                                        row.title_en,
                                                    )}
                                                </Link>
                                                <p className="text-xs text-muted-foreground">
                                                    {t(
                                                        `documents.kind.${row.kind}`,
                                                    )}
                                                    {' · '}
                                                    <span className="font-mono">
                                                        {row.reference}
                                                    </span>
                                                </p>
                                            </td>
                                            <td className="p-3 text-muted-foreground tabular-nums">
                                                {date(row.at)}
                                            </td>
                                            <td className="p-3">
                                                {row.representative}
                                            </td>
                                            <td className="p-3 text-muted-foreground">
                                                {row.method &&
                                                    t(
                                                        `agreement.method.${row.method}`,
                                                    )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

function Section({
    icon: Icon,
    title,
    children,
}: {
    icon: typeof FileSignature;
    title: string;
    children: React.ReactNode;
}) {
    return (
        <section className="space-y-3 rounded-xl border bg-card p-5">
            <h2 className="flex items-center gap-2 text-sm font-extrabold">
                <Icon className="size-4 text-primary" aria-hidden="true" />
                {title}
            </h2>
            {children}
        </section>
    );
}

function OfferRow({
    offer,
    cta,
    highlight,
}: {
    offer: Offer;
    cta: string;
    highlight?: boolean;
}) {
    const t = useTranslation();
    const { locale } = useLocale();

    return (
        <li
            className={
                highlight
                    ? 'flex flex-wrap items-center justify-between gap-3 rounded-lg border border-primary/40 bg-primary/5 p-3'
                    : 'flex flex-wrap items-center justify-between gap-3'
            }
        >
            <div className="min-w-0">
                <p className="flex items-center gap-2 font-medium">
                    {localised(locale, offer.title_ar, offer.title_en)}
                    <OfferStatusBadge status={offer.status} />
                </p>
                <p className="text-xs text-muted-foreground">
                    {highlight
                        ? t('documents.offer_pending')
                        : t('documents.offer_current')}
                </p>
            </div>
            <Button
                asChild
                size="sm"
                variant={highlight ? 'default' : 'outline'}
            >
                <Link href={`/owner/commercial-offers/${offer.id}`}>{cta}</Link>
            </Button>
        </li>
    );
}

OwnerDocuments.layout = {
    breadcrumbs: [{ title: 'documents.title', href: '/owner/agreements' }],
};
