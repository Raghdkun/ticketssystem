import { Form, Head, Link, router } from '@inertiajs/react';
import { Handshake, Plus } from 'lucide-react';
import { useState } from 'react';
import {
    OfferFields,
    SELECT_CLASS,
} from '@/components/commercial/offer-fields';
import type { PlaceOption } from '@/components/commercial/offer-fields';
import type { Offer } from '@/components/commercial/offer-summary';
import { OfferStatusBadge } from '@/components/commercial/offer-summary';
import { EmptyState } from '@/components/empty-state';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { dateTag } from '@/lib/format';
import { localised, useLocale } from '@/lib/locale';
import { useTranslation } from '@/lib/translation';

type Props = {
    offers: Offer[];
    places: PlaceOption[];
    filter: string;
    fee_types: string[];
    fee_payers: string[];
};

export default function AdminCommercialOffers({
    offers,
    places,
    filter,
    fee_types,
    fee_payers,
}: Props) {
    const t = useTranslation();
    const { locale } = useLocale();
    const dateLocale = dateTag(locale);
    const [drafting, setDrafting] = useState(false);

    return (
        <>
            <Head title={t('commercial.admin.title')} />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        variant="small"
                        title={t('commercial.admin.title')}
                        description={t('commercial.admin.subtitle')}
                    />
                    <div className="flex flex-wrap items-center gap-2">
                        <select
                            aria-label={t('commercial.admin.venue')}
                            value={filter}
                            onChange={(e) =>
                                router.get(
                                    '/admin/commercial-offers',
                                    e.target.value
                                        ? { place: e.target.value }
                                        : {},
                                    { preserveState: true },
                                )
                            }
                            className={SELECT_CLASS + ' w-auto'}
                        >
                            <option value="">
                                {t('commercial.admin.all_venues')}
                            </option>
                            {places.map((place) => (
                                <option key={place.id} value={place.slug}>
                                    {localised(
                                        locale,
                                        place.name_ar,
                                        place.name_en,
                                    )}
                                </option>
                            ))}
                        </select>
                        <Button
                            type="button"
                            onClick={() => setDrafting((open) => !open)}
                            aria-expanded={drafting}
                        >
                            <Plus />
                            {t('commercial.admin.new')}
                        </Button>
                    </div>
                </div>

                {drafting && (
                    <Form
                        action="/admin/commercial-offers"
                        method="post"
                        className="space-y-4 rounded-xl border p-4 sm:p-6"
                    >
                        {({ processing, errors }) => (
                            <>
                                <OfferFields
                                    places={places}
                                    feeTypes={fee_types}
                                    feePayers={fee_payers}
                                    errors={errors}
                                />
                                <Button type="submit" disabled={processing}>
                                    {processing ? <Spinner /> : <Handshake />}
                                    {t('commercial.admin.create')}
                                </Button>
                            </>
                        )}
                    </Form>
                )}

                {offers.length === 0 ? (
                    <EmptyState
                        icon={Handshake}
                        title={t('commercial.admin.none')}
                    />
                ) : (
                    <ul className="divide-y rounded-xl border">
                        {offers.map((offer) => (
                            <li key={offer.id}>
                                <Link
                                    href={`/admin/commercial-offers/${offer.id}`}
                                    className="flex min-h-11 flex-wrap items-center justify-between gap-3 p-4 transition-colors hover:bg-muted/50"
                                >
                                    <div className="min-w-0">
                                        <p className="flex items-center gap-2 font-semibold">
                                            {localised(
                                                locale,
                                                offer.place.name_ar,
                                                offer.place.name_en,
                                            )}
                                            <OfferStatusBadge
                                                status={offer.status}
                                            />
                                        </p>
                                        <p className="mt-0.5 truncate text-sm text-muted-foreground">
                                            {localised(
                                                locale,
                                                offer.title_ar,
                                                offer.title_en,
                                            )}
                                            {' · '}
                                            {offer.fee_type === 'percentage' &&
                                            offer.fee_value !== null
                                                ? `${offer.fee_value}%`
                                                : t(
                                                      `commercial.fee_types.${offer.fee_type}`,
                                                  )}
                                            {' · '}
                                            {t(
                                                `commercial.fee_payers.${offer.fee_payer}`,
                                            )}
                                        </p>
                                    </div>
                                    <span className="text-xs text-muted-foreground tabular-nums">
                                        {(offer.accepted_at ?? offer.sent_at) &&
                                            new Date(
                                                (offer.accepted_at ??
                                                    offer.sent_at) as string,
                                            ).toLocaleDateString(dateLocale, {
                                                dateStyle: 'medium',
                                            })}
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

AdminCommercialOffers.layout = {
    breadcrumbs: [
        { title: 'commercial.admin.title', href: '/admin/commercial-offers' },
    ],
};
