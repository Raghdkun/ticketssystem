import { Form, Head } from '@inertiajs/react';
import { Handshake } from 'lucide-react';
import { useState } from 'react';
import type { Offer } from '@/components/commercial/offer-summary';
import {
    OfferStatusBadge,
    OfferSummary,
    SignatureRecord,
} from '@/components/commercial/offer-summary';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { HoldSubmit } from '@/components/motion/hold-submit';
import { Spark } from '@/components/motion/spark';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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

/**
 * A commercial offer, read and answered by the venue.
 *
 * The terms are a table, not a wall of text; the checkbox starts unticked
 * and names the offer as an annex to the Terms; declining is a quiet
 * secondary control behind a confirmation.
 */
export default function OwnerCommercialOffer({ offer }: { offer: Offer }) {
    const t = useTranslation();
    const { locale } = useLocale();
    const [ticked, setTicked] = useState(false);
    const [declining, setDeclining] = useState(false);
    const open = offer.status === 'sent';

    return (
        <>
            <Head title={t('commercial.title')} />

            <div className="max-w-3xl space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading
                        variant="small"
                        title={localised(
                            locale,
                            offer.title_ar,
                            offer.title_en,
                        )}
                        description={t('commercial.summary')}
                    />
                    <OfferStatusBadge status={offer.status} />
                </div>

                <OfferSummary offer={offer} />

                <SignatureRecord
                    title={t('commercial.record')}
                    signature={offer.signature}
                    acceptedAt={offer.accepted_at}
                    hash={offer.content_hash}
                />

                {open && (
                    <Form
                        action={`/owner/commercial-offers/${offer.id}/accept`}
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
                                    <span>{t('commercial.accept_label')}</span>
                                </label>
                                <InputError message={errors.accept} />

                                <div className="flex flex-wrap items-center gap-3">
                                    <Spark>
                                        <Button
                                            type="submit"
                                            size="lg"
                                            disabled={processing || !ticked}
                                        >
                                            {processing ? (
                                                <Spinner />
                                            ) : (
                                                <Handshake />
                                            )}
                                            {t('commercial.accept_button')}
                                        </Button>
                                    </Spark>

                                    <Dialog
                                        open={declining}
                                        onOpenChange={setDeclining}
                                    >
                                        <DialogTrigger asChild>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                            >
                                                {t('commercial.reject_button')}
                                            </Button>
                                        </DialogTrigger>
                                        <DialogContent>
                                            <DialogTitle>
                                                {t('commercial.reject_button')}
                                            </DialogTitle>
                                            <DialogDescription>
                                                {t('commercial.reject_confirm')}
                                            </DialogDescription>
                                            <Form
                                                action={`/owner/commercial-offers/${offer.id}/reject`}
                                                method="post"
                                            >
                                                {({
                                                    processing: rejecting,
                                                }) => (
                                                    <DialogFooter className="gap-2">
                                                        <DialogClose asChild>
                                                            <Button
                                                                variant="outline"
                                                                type="button"
                                                            >
                                                                {t(
                                                                    'common.cancel',
                                                                )}
                                                            </Button>
                                                        </DialogClose>
                                                        <HoldSubmit
                                                            disabled={rejecting}
                                                            doneLabel={t(
                                                                'common.done',
                                                            )}
                                                        >
                                                            {t(
                                                                'common.hold_to_confirm',
                                                            )}
                                                        </HoldSubmit>
                                                    </DialogFooter>
                                                )}
                                            </Form>
                                        </DialogContent>
                                    </Dialog>
                                </div>
                            </>
                        )}
                    </Form>
                )}
            </div>
        </>
    );
}

OwnerCommercialOffer.layout = {
    breadcrumbs: [
        { title: 'documents.title', href: '/owner/agreements' },
        { title: 'commercial.title', href: '#' },
    ],
};
