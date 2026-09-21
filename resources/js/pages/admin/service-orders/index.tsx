import { Form, Head, Link, router } from '@inertiajs/react';
import { PackageCheck, Plus } from 'lucide-react';
import { useState } from 'react';
import { SELECT_CLASS } from '@/components/commercial/offer-fields';
import type { PlaceOption } from '@/components/commercial/offer-fields';
import { OrderFields } from '@/components/commercial/order-fields';
import type { Order } from '@/components/commercial/order-summary';
import { OrderStatusBadge } from '@/components/commercial/order-summary';
import { EmptyState } from '@/components/empty-state';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { formatMoney } from '@/lib/format';
import { localised, useLocale } from '@/lib/locale';
import { useTranslation } from '@/lib/translation';

type Props = {
    orders: Order[];
    places: PlaceOption[];
    filter: { place: string; status: string };
    types: string[];
    statuses: string[];
};

export default function AdminServiceOrders({
    orders,
    places,
    filter,
    types,
    statuses,
}: Props) {
    const t = useTranslation();
    const { locale } = useLocale();
    const [drafting, setDrafting] = useState(false);

    const refilter = (next: Partial<Props['filter']>) => {
        const params = { ...filter, ...next };
        router.get(
            '/admin/service-orders',
            Object.fromEntries(
                Object.entries(params).filter(([, v]) => v !== ''),
            ),
            { preserveState: true },
        );
    };

    return (
        <>
            <Head title={t('orders.admin.title')} />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        variant="small"
                        title={t('orders.admin.title')}
                        description={t('orders.admin.subtitle')}
                    />
                    <div className="flex flex-wrap items-center gap-2">
                        <select
                            aria-label={t('orders.admin.venue')}
                            value={filter.place}
                            onChange={(e) =>
                                refilter({ place: e.target.value })
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
                        <select
                            aria-label={t('form.status')}
                            value={filter.status}
                            onChange={(e) =>
                                refilter({ status: e.target.value })
                            }
                            className={SELECT_CLASS + ' w-auto'}
                        >
                            <option value="">
                                {t('orders.admin.all_statuses')}
                            </option>
                            {statuses.map((status) => (
                                <option key={status} value={status}>
                                    {t(`orders.status.${status}`)}
                                </option>
                            ))}
                        </select>
                        <Button
                            type="button"
                            onClick={() => setDrafting((open) => !open)}
                            aria-expanded={drafting}
                        >
                            <Plus />
                            {t('orders.admin.new')}
                        </Button>
                    </div>
                </div>

                {drafting && (
                    <Form
                        action="/admin/service-orders"
                        method="post"
                        className="space-y-4 rounded-xl border p-4 sm:p-6"
                    >
                        {({ processing, errors }) => (
                            <>
                                <OrderFields
                                    places={places}
                                    events={[]}
                                    types={types}
                                    errors={errors}
                                />
                                <Button type="submit" disabled={processing}>
                                    {processing ? (
                                        <Spinner />
                                    ) : (
                                        <PackageCheck />
                                    )}
                                    {t('orders.admin.create')}
                                </Button>
                            </>
                        )}
                    </Form>
                )}

                {orders.length === 0 ? (
                    <EmptyState
                        icon={PackageCheck}
                        title={t('orders.admin.none')}
                    />
                ) : (
                    <ul className="divide-y rounded-xl border">
                        {orders.map((order) => (
                            <li key={order.id}>
                                <Link
                                    href={`/admin/service-orders/${order.id}`}
                                    className="flex min-h-11 flex-wrap items-center justify-between gap-3 p-4 transition-colors hover:bg-muted/50"
                                >
                                    <div className="min-w-0">
                                        <p className="flex items-center gap-2 font-semibold">
                                            {localised(
                                                locale,
                                                order.title_ar,
                                                order.title_en,
                                            )}
                                            <OrderStatusBadge
                                                status={order.status}
                                            />
                                        </p>
                                        <p className="mt-0.5 truncate text-sm text-muted-foreground">
                                            {localised(
                                                locale,
                                                order.place.name_ar,
                                                order.place.name_en,
                                            )}
                                            {' · '}
                                            {t(
                                                `orders.types.${order.service_type}`,
                                            )}
                                            {order.event &&
                                                ` · ${localised(locale, order.event.title_ar, order.event.title_en)}`}
                                        </p>
                                    </div>
                                    {order.total !== null && (
                                        <span className="text-sm font-semibold tabular-nums">
                                            {formatMoney(
                                                order.total,
                                                order.currency ?? '',
                                            )}
                                        </span>
                                    )}
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

AdminServiceOrders.layout = {
    breadcrumbs: [
        { title: 'orders.admin.title', href: '/admin/service-orders' },
    ],
};
