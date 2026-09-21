import { Form, Head } from '@inertiajs/react';
import { PackageCheck } from 'lucide-react';
import { useState } from 'react';
import { SignatureRecord } from '@/components/commercial/offer-summary';
import type { Order } from '@/components/commercial/order-summary';
import {
    OrderStatusBadge,
    OrderSummary,
} from '@/components/commercial/order-summary';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Spinner } from '@/components/ui/spinner';
import { localised, useLocale } from '@/lib/locale';
import { useTranslation } from '@/lib/translation';

/**
 * A service order, read and confirmed by the venue.
 */
export default function OwnerServiceOrder({ order }: { order: Order }) {
    const t = useTranslation();
    const { locale } = useLocale();
    const [ticked, setTicked] = useState(false);

    return (
        <>
            <Head title={t('orders.title')} />

            <div className="max-w-3xl space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading
                        variant="small"
                        title={localised(
                            locale,
                            order.title_ar,
                            order.title_en,
                        )}
                        description={t('orders.title')}
                    />
                    <OrderStatusBadge status={order.status} />
                </div>

                <OrderSummary order={order} />

                <SignatureRecord
                    title={t('orders.record')}
                    signature={order.signature}
                    acceptedAt={order.accepted_at}
                    hash={order.content_hash}
                />

                {order.status === 'sent' && (
                    <Form
                        action={`/owner/service-orders/${order.id}/accept`}
                        method="post"
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <label
                                    htmlFor="accept"
                                    className="flex min-h-11 cursor-pointer items-start gap-3 rounded-xl border p-4 text-sm"
                                >
                                    <Checkbox
                                        id="accept"
                                        name="accept"
                                        value="1"
                                        checked={ticked}
                                        onCheckedChange={(value) =>
                                            setTicked(value === true)
                                        }
                                        className="mt-0.5 cursor-pointer"
                                    />
                                    <span>{t('orders.accept_label')}</span>
                                </label>
                                <InputError message={errors.accept} />

                                <Button
                                    type="submit"
                                    size="lg"
                                    disabled={processing || !ticked}
                                >
                                    {processing ? (
                                        <Spinner />
                                    ) : (
                                        <PackageCheck />
                                    )}
                                    {t('orders.accept_button')}
                                </Button>
                            </>
                        )}
                    </Form>
                )}
            </div>
        </>
    );
}

OwnerServiceOrder.layout = {
    breadcrumbs: [
        { title: 'documents.title', href: '/owner/agreements' },
        { title: 'orders.title', href: '#' },
    ],
};
