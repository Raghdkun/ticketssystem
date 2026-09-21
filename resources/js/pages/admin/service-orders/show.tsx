import { Form, Head } from '@inertiajs/react';
import { Lock, Save, Send, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { PlaceOption } from '@/components/commercial/offer-fields';
import { SignatureRecord } from '@/components/commercial/offer-summary';
import { OrderFields } from '@/components/commercial/order-fields';
import type { EventOption } from '@/components/commercial/order-fields';
import type { Order } from '@/components/commercial/order-summary';
import {
    OrderStatusBadge,
    OrderSummary,
} from '@/components/commercial/order-summary';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { localised, useLocale } from '@/lib/locale';
import { useTranslation } from '@/lib/translation';

type Props = {
    order: Order;
    places: PlaceOption[];
    events: EventOption[];
    types: string[];
};

const NEXT: Record<
    Order['status'],
    ('in_progress' | 'completed' | 'cancelled')[]
> = {
    draft: [],
    sent: ['cancelled'],
    accepted: ['in_progress', 'completed', 'cancelled'],
    in_progress: ['completed', 'cancelled'],
    completed: [],
    cancelled: [],
};

/**
 * One order: a form while a draft, a sealed record afterwards, and the
 * status controls an administrator works it through with.
 */
export default function AdminServiceOrder({
    order,
    places,
    events,
    types,
}: Props) {
    const t = useTranslation();
    const { locale } = useLocale();
    const draft = order.status === 'draft';
    const [confirmSend, setConfirmSend] = useState(false);

    return (
        <>
            <Head title={`${t('orders.admin.title')} #${order.id}`} />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        variant="small"
                        title={localised(
                            locale,
                            order.title_ar,
                            order.title_en,
                        )}
                        description={localised(
                            locale,
                            order.place.name_ar,
                            order.place.name_en,
                        )}
                    />
                    <OrderStatusBadge status={order.status} />
                </div>

                {draft ? (
                    <Form
                        action={`/admin/service-orders/${order.id}`}
                        method="patch"
                        options={{ preserveScroll: true }}
                        className="space-y-4 rounded-xl border p-4 sm:p-6"
                    >
                        {({ processing, errors }) => (
                            <>
                                <OrderFields
                                    order={order}
                                    places={places}
                                    events={events}
                                    types={types}
                                    errors={errors}
                                />
                                <Button type="submit" disabled={processing}>
                                    {processing ? <Spinner /> : <Save />}
                                    {t('orders.admin.save')}
                                </Button>
                            </>
                        )}
                    </Form>
                ) : (
                    <>
                        <p className="flex items-center gap-2 rounded-xl border bg-muted/40 p-3 text-sm text-muted-foreground">
                            <Lock
                                className="size-4 shrink-0"
                                aria-hidden="true"
                            />
                            {t('orders.admin.immutable')}
                            {order.content_hash && (
                                <span
                                    dir="ltr"
                                    className="ms-auto font-mono text-xs"
                                >
                                    {order.content_hash.slice(0, 16)}
                                </span>
                            )}
                        </p>
                        <OrderSummary order={order} />
                        <SignatureRecord
                            title={t('orders.record')}
                            signature={order.signature}
                            acceptedAt={order.accepted_at}
                            hash={order.content_hash}
                        />
                    </>
                )}

                <div className="flex flex-wrap items-center gap-3">
                    {draft && (
                        <>
                            <Dialog
                                open={confirmSend}
                                onOpenChange={setConfirmSend}
                            >
                                <DialogTrigger asChild>
                                    <Button type="button">
                                        <Send />
                                        {t('orders.admin.send')}
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogTitle>
                                        {t('orders.admin.send')}
                                    </DialogTitle>
                                    <DialogDescription>
                                        {t('orders.admin.send_confirm')}
                                    </DialogDescription>
                                    <Form
                                        action={`/admin/service-orders/${order.id}/send`}
                                        method="post"
                                        onSuccess={() => setConfirmSend(false)}
                                    >
                                        {({ processing }) => (
                                            <DialogFooter className="gap-2">
                                                <DialogClose asChild>
                                                    <Button
                                                        variant="outline"
                                                        type="button"
                                                    >
                                                        {t('common.cancel')}
                                                    </Button>
                                                </DialogClose>
                                                <Button
                                                    type="submit"
                                                    disabled={processing}
                                                >
                                                    {t('orders.admin.send')}
                                                </Button>
                                            </DialogFooter>
                                        )}
                                    </Form>
                                </DialogContent>
                            </Dialog>

                            <Form
                                action={`/admin/service-orders/${order.id}`}
                                method="delete"
                            >
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        variant="ghost"
                                        disabled={processing}
                                        className="text-destructive hover:text-destructive"
                                    >
                                        <Trash2 />
                                        {t('orders.admin.delete')}
                                    </Button>
                                )}
                            </Form>
                        </>
                    )}

                    {NEXT[order.status].map((status) => (
                        <Form
                            key={status}
                            action={`/admin/service-orders/${order.id}/status`}
                            method="post"
                            options={{ preserveScroll: true }}
                        >
                            {({ processing }) => (
                                <>
                                    <input
                                        type="hidden"
                                        name="status"
                                        value={status}
                                    />
                                    <Button
                                        type="submit"
                                        variant={
                                            status === 'cancelled'
                                                ? 'ghost'
                                                : 'outline'
                                        }
                                        disabled={processing}
                                        className={
                                            status === 'cancelled'
                                                ? 'text-destructive hover:text-destructive'
                                                : undefined
                                        }
                                    >
                                        {t(`orders.admin.mark_${status}`)}
                                    </Button>
                                </>
                            )}
                        </Form>
                    ))}
                </div>
            </div>
        </>
    );
}

AdminServiceOrder.layout = {
    breadcrumbs: [
        { title: 'orders.admin.title', href: '/admin/service-orders' },
        { title: 'orders.title', href: '#' },
    ],
};
