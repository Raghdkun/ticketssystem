import { Form, Head, Link } from '@inertiajs/react';
import { Lock, Save, Send, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { OfferFields } from '@/components/commercial/offer-fields';
import type { PlaceOption } from '@/components/commercial/offer-fields';
import type { Offer } from '@/components/commercial/offer-summary';
import {
    OfferStatusBadge,
    OfferSummary,
    SignatureRecord,
} from '@/components/commercial/offer-summary';
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
import { dateTag } from '@/lib/format';
import { localised, useLocale } from '@/lib/locale';
import { useTranslation } from '@/lib/translation';

type Props = {
    offer: Offer;
    places: PlaceOption[];
    fee_types: string[];
    fee_payers: string[];
    snapshots: {
        event_id: number;
        title_ar: string | null;
        title_en: string | null;
        accepted_at: string;
    }[];
};

/**
 * One offer: a form while a draft, a sealed record afterwards, with the
 * venue's answer and the events published under it.
 */
export default function AdminCommercialOffer({
    offer,
    places,
    fee_types,
    fee_payers,
    snapshots,
}: Props) {
    const t = useTranslation();
    const { locale } = useLocale();
    const dateLocale = dateTag(locale);
    const draft = offer.status === 'draft';
    const [confirmSend, setConfirmSend] = useState(false);

    return (
        <>
            <Head title={`${t('commercial.admin.title')} #${offer.id}`} />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        variant="small"
                        title={localised(
                            locale,
                            offer.place.name_ar,
                            offer.place.name_en,
                        )}
                        description={localised(
                            locale,
                            offer.title_ar,
                            offer.title_en,
                        )}
                    />
                    <OfferStatusBadge status={offer.status} />
                </div>

                {draft ? (
                    <Form
                        action={`/admin/commercial-offers/${offer.id}`}
                        method="patch"
                        options={{ preserveScroll: true }}
                        className="space-y-4 rounded-xl border p-4 sm:p-6"
                    >
                        {({ processing, errors }) => (
                            <>
                                <OfferFields
                                    offer={offer}
                                    places={places}
                                    feeTypes={fee_types}
                                    feePayers={fee_payers}
                                    errors={errors}
                                />
                                <Button type="submit" disabled={processing}>
                                    {processing ? <Spinner /> : <Save />}
                                    {t('commercial.admin.save')}
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
                            {t('commercial.admin.immutable')}
                            {offer.content_hash && (
                                <span
                                    dir="ltr"
                                    className="ms-auto font-mono text-xs"
                                >
                                    {offer.content_hash.slice(0, 16)}
                                </span>
                            )}
                        </p>
                        <OfferSummary offer={offer} />
                        <SignatureRecord
                            title={t('commercial.record')}
                            signature={offer.signature}
                            acceptedAt={offer.accepted_at}
                            hash={offer.content_hash}
                        />
                    </>
                )}

                {draft && (
                    <div className="flex flex-wrap items-center gap-3">
                        <Dialog
                            open={confirmSend}
                            onOpenChange={setConfirmSend}
                        >
                            <DialogTrigger asChild>
                                <Button type="button">
                                    <Send />
                                    {t('commercial.admin.send')}
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogTitle>
                                    {t('commercial.admin.send')}
                                </DialogTitle>
                                <DialogDescription>
                                    {t('commercial.admin.send_confirm')}
                                </DialogDescription>
                                <Form
                                    action={`/admin/commercial-offers/${offer.id}/send`}
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
                                                {t('commercial.admin.send')}
                                            </Button>
                                        </DialogFooter>
                                    )}
                                </Form>
                            </DialogContent>
                        </Dialog>

                        <Form
                            action={`/admin/commercial-offers/${offer.id}`}
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
                                    {t('commercial.admin.delete')}
                                </Button>
                            )}
                        </Form>
                    </div>
                )}

                {!draft && (
                    <section className="space-y-3">
                        <h2 className="text-sm font-extrabold">
                            {t('commercial.admin.events_under')}
                        </h2>
                        {snapshots.length === 0 ? (
                            <p className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                                {t('commercial.admin.no_events')}
                            </p>
                        ) : (
                            <ul className="divide-y rounded-xl border">
                                {snapshots.map((snapshot) => (
                                    <li
                                        key={snapshot.event_id}
                                        className="flex flex-wrap items-center justify-between gap-3 p-3 text-sm"
                                    >
                                        <Link
                                            href={`/admin/events`}
                                            className="font-medium underline-offset-4 hover:underline"
                                        >
                                            {localised(
                                                locale,
                                                snapshot.title_ar,
                                                snapshot.title_en,
                                            ) ?? '—'}
                                        </Link>
                                        <span className="text-muted-foreground tabular-nums">
                                            {new Date(
                                                snapshot.accepted_at,
                                            ).toLocaleDateString(dateLocale, {
                                                dateStyle: 'medium',
                                            })}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                )}
            </div>
        </>
    );
}

AdminCommercialOffer.layout = {
    breadcrumbs: [
        { title: 'commercial.admin.title', href: '/admin/commercial-offers' },
        { title: 'commercial.title', href: '#' },
    ],
};
