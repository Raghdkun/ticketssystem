import { Form, Link, router, usePage } from '@inertiajs/react';
import {
    Archive,
    CheckCircle2,
    Clock,
    FileEdit,
    Pencil,
    Send,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
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
    DialogTitle,
} from '@/components/ui/dialog';
import { dateTag, formatMoney } from '@/lib/format';
import { localised, useLocale } from '@/lib/locale';
import { useTranslation } from '@/lib/translation';

export type SavedEvent = {
    id: number;
    status: 'draft' | 'published' | 'pending_review' | 'archived';
    title_ar: string;
    title_en: string;
    url: string | null;
    edit_url: string;
    unlisted: boolean;
    copies: number;
    requires_approval: boolean;
    summary: {
        starts_at: string;
        price: number;
        currency: string;
        is_free: boolean;
        total_quantity: number | null;
        location: string | null;
        host: string | null;
        auto_confirm: boolean;
    };
    needs_ack: boolean;
    commercial: {
        fee_type: string;
        fee_value: number | null;
        fee_payer: string;
        settlement_days: number | null;
        currency: string | null;
    } | null;
};

const ICONS = {
    draft: FileEdit,
    published: CheckCircle2,
    pending_review: Clock,
    archived: Archive,
} as const;

/**
 * What just happened to the event the owner saved, and what to do next.
 *
 * The form no longer carries a status: saving saves, and this is where a
 * draft goes live, stays a draft, or is thrown away. A summary sits above
 * the buttons so the owner reads what they are about to publish -- dates,
 * price, seats, where it is, and the commercial terms it will be sold
 * under -- rather than a word in a select nobody understood.
 *
 * The flash lives exactly one request, so the dialog is open while the
 * payload is there and has not been dismissed. Dismissal is remembered by
 * identity rather than cleared with an effect: the same payload object
 * survives a partial reload, and a dialog that reopened on every filter
 * click would be worse than the toast it replaced.
 */
export function SavedEventDialog() {
    const t = useTranslation();
    const { locale } = useLocale();
    const flash = usePage<{ flash?: { saved_event?: SavedEvent } }>().props
        .flash;
    const [dismissed, setDismissed] = useState<SavedEvent | null>(null);
    const [ack, setAck] = useState(false);
    const saved =
        flash?.saved_event && flash.saved_event !== dismissed
            ? flash.saved_event
            : null;

    if (saved === null) {
        return null;
    }

    const Icon = ICONS[saved.status];
    const body =
        saved.status === 'published' && saved.unlisted
            ? t('owner.saved.published_unlisted_body')
            : t(`owner.saved.${saved.status}_body`);
    const copiesLine =
        saved.copies > 0
            ? saved.status === 'pending_review'
                ? t('owner.saved.copies_pending', { count: saved.copies })
                : t('owner.saved.copies', { count: saved.copies })
            : null;
    const s = saved.summary;
    const dateLocale = dateTag(locale);
    const c = saved.commercial;
    const fee =
        c === null
            ? null
            : c.fee_type === 'percentage' && c.fee_value !== null
              ? `${c.fee_value}%`
              : c.fee_value !== null
                ? formatMoney(c.fee_value, c.currency ?? '')
                : t(`commercial.fee_types.${c.fee_type}`);

    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open) {
                    setDismissed(saved);
                    setAck(false);
                }
            }}
        >
            <DialogContent className="max-h-[92dvh] overflow-y-auto">
                <div className="flex items-start gap-3">
                    <span className="grid size-10 shrink-0 place-items-center rounded-full bg-primary text-primary-foreground">
                        <Icon className="size-5" aria-hidden="true" />
                    </span>
                    <div className="min-w-0 space-y-1">
                        <DialogTitle>
                            {t(`owner.saved.${saved.status}`)}
                        </DialogTitle>
                        <p className="truncate text-sm font-semibold">
                            {localised(locale, saved.title_ar, saved.title_en)}
                        </p>
                        <DialogDescription>{body}</DialogDescription>
                        {copiesLine && (
                            <p className="text-sm text-muted-foreground">
                                {copiesLine}
                            </p>
                        )}
                    </div>
                </div>

                {/* The summary: what is about to go out, in the order a
                    buyer meets it. */}
                <dl className="grid grid-cols-2 gap-x-4 gap-y-3 rounded-xl border bg-muted/30 p-4 text-sm">
                    <Row
                        label={t('owner.saved.when')}
                        value={new Date(s.starts_at).toLocaleString(
                            dateLocale,
                            { dateStyle: 'medium', timeStyle: 'short' },
                        )}
                    />
                    <Row
                        label={t('owner.saved.where')}
                        value={
                            s.host
                                ? `${s.location} · ${s.host}`
                                : (s.location ?? t('owner.saved.no_location'))
                        }
                    />
                    <Row
                        label={t('owner.saved.price')}
                        value={
                            s.is_free
                                ? t('event.free')
                                : formatMoney(s.price, s.currency)
                        }
                    />
                    <Row
                        label={t('owner.saved.seats')}
                        value={
                            s.total_quantity === null
                                ? t('owner.saved.seats_unlimited')
                                : String(s.total_quantity)
                        }
                    />
                    <Row
                        label={t('owner.saved.visibility')}
                        value={
                            saved.unlisted
                                ? t('owner.saved.visible_unlisted')
                                : t('owner.saved.visible_listed')
                        }
                    />
                    {s.auto_confirm && (
                        <Row
                            label={t('form.auto_confirm')}
                            value={t('owner.saved.auto_confirm')}
                        />
                    )}
                    {c && fee && (
                        <>
                            <Row label={t('commercial.fee')} value={fee} />
                            <Row
                                label={t('commercial.fee_payer')}
                                value={t(
                                    `commercial.fee_payers.${c.fee_payer}`,
                                )}
                            />
                        </>
                    )}
                </dl>

                {saved.status === 'draft' && (
                    <Form
                        action={`/owner/events/${saved.id}/publish`}
                        method="post"
                        options={{ preserveScroll: true }}
                        className="space-y-3"
                    >
                        {({ processing, errors }) => (
                            <>
                                {saved.needs_ack && (
                                    <>
                                        <label
                                            htmlFor="dialog_commercial_ack"
                                            className="flex min-h-11 cursor-pointer items-start gap-3 rounded-xl border border-primary/40 bg-primary/5 p-3 text-sm"
                                        >
                                            <Checkbox
                                                id="dialog_commercial_ack"
                                                name="commercial_ack"
                                                value="1"
                                                checked={ack}
                                                onCheckedChange={(v) =>
                                                    setAck(v === true)
                                                }
                                                className="mt-0.5 cursor-pointer"
                                            />
                                            {t('commercial.ack_label')}
                                        </label>
                                        <InputError
                                            message={errors.commercial_ack}
                                        />
                                    </>
                                )}

                                <div className="flex flex-wrap items-center gap-2">
                                    <Spark>
                                        <Button
                                            type="submit"
                                            disabled={
                                                processing ||
                                                (saved.needs_ack && !ack)
                                            }
                                        >
                                            <Send />
                                            {saved.requires_approval
                                                ? t(
                                                      'owner.saved.send_for_review',
                                                  )
                                                : t('owner.saved.publish_now')}
                                        </Button>
                                    </Spark>
                                    <Button asChild variant="outline">
                                        <Link href={saved.edit_url}>
                                            <Pencil />
                                            {t('owner.saved.keep_editing')}
                                        </Link>
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                )}

                {saved.status === 'draft' && (
                    <div className="flex flex-wrap items-center gap-2 border-t pt-3">
                        {/* Hold, not click: a draft deleted by a slip of the
                            thumb is not recoverable. */}
                        <Form
                            action={`/owner/events/${saved.id}`}
                            method="delete"
                        >
                            {({ processing }) => (
                                <HoldSubmit
                                    disabled={processing}
                                    doneLabel={t('common.done')}
                                >
                                    <Trash2
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    {t('owner.saved.discard')}
                                </HoldSubmit>
                            )}
                        </Form>
                        <DialogClose asChild>
                            <Button variant="ghost" size="sm" type="button">
                                {t('common.close')}
                            </Button>
                        </DialogClose>
                    </div>
                )}

                {saved.status !== 'draft' && (
                    <div className="flex flex-wrap items-center gap-2">
                        {saved.url && (
                            <Button
                                type="button"
                                onClick={() =>
                                    router.visit(saved.url as string)
                                }
                            >
                                {t('owner.saved.view')}
                            </Button>
                        )}
                        <Button asChild variant="outline">
                            <Link href={saved.edit_url}>
                                <Pencil />
                                {t('owner.saved.keep_editing')}
                            </Link>
                        </Button>
                        {(saved.status === 'published' ||
                            saved.status === 'pending_review') && (
                            <Form
                                action={`/owner/events/${saved.id}/unpublish`}
                                method="post"
                            >
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        variant="ghost"
                                        disabled={processing}
                                        className="text-muted-foreground"
                                    >
                                        {t('owner.saved.unpublish')}
                                    </Button>
                                )}
                            </Form>
                        )}
                        <DialogClose asChild>
                            <Button variant="ghost" type="button">
                                {t('common.close')}
                            </Button>
                        </DialogClose>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="min-w-0">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="truncate font-medium">{value}</dd>
        </div>
    );
}
