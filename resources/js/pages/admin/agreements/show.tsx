import { Form, Head } from '@inertiajs/react';
import { Lock, Save, Send, Trash2 } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { dateTag } from '@/lib/format';
import { localised, useLocale } from '@/lib/locale';
import { useTranslation } from '@/lib/translation';
import { VersionStatusBadge } from './index';
import type { VersionSummary } from './index';

type Agreement = VersionSummary & {
    body_ar: string;
    body_en: string;
    change_note_ar: string | null;
    change_note_en: string | null;
    content_hash: string | null;
    published_by: string | null;
};

type Acceptance = {
    id: number;
    place_ar: string;
    place_en: string;
    legal_name: string;
    representative_name: string;
    representative_title: string | null;
    representative_phone: string;
    accepted_by: string | null;
    accepted_at: string;
    otp_channel: string;
    ip: string | null;
};

/**
 * One version: editable while a draft, a sealed record afterwards, with
 * every acceptance of it underneath.
 */
export default function AdminAgreement({
    agreement,
    acceptances,
}: {
    agreement: Agreement;
    acceptances: Acceptance[];
}) {
    const t = useTranslation();
    const { locale } = useLocale();
    const dateLocale = dateTag(locale);
    const draft = agreement.status === 'draft';
    const [confirmPublish, setConfirmPublish] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState(false);

    const formatDate = (iso: string) =>
        new Date(iso).toLocaleString(dateLocale, {
            dateStyle: 'medium',
            timeStyle: 'short',
        });

    return (
        <>
            <Head
                title={`${t('agreement.admin.title')} ${agreement.version}`}
            />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        variant="small"
                        title={`${localised(locale, agreement.title_ar, agreement.title_en)} · ${agreement.version}`}
                        description={
                            agreement.published_at
                                ? t('agreement.admin.published_by', {
                                      name: agreement.published_by ?? '—',
                                      date: formatDate(agreement.published_at),
                                  }) +
                                  (agreement.retired_at
                                      ? ' · ' +
                                        t('agreement.admin.retired_on', {
                                            date: formatDate(
                                                agreement.retired_at,
                                            ),
                                        })
                                      : '')
                                : t('agreement.admin.subtitle')
                        }
                    />
                    <VersionStatusBadge status={agreement.status} />
                </div>

                {draft ? (
                    <Form
                        action={`/admin/agreements/${agreement.id}`}
                        method="patch"
                        options={{ preserveScroll: true }}
                        className="space-y-4 rounded-xl border p-4 sm:p-6"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-4 sm:grid-cols-3">
                                    <div className="grid gap-2">
                                        <Label htmlFor="version">
                                            {t('agreement.admin.version_label')}
                                        </Label>
                                        <Input
                                            id="version"
                                            name="version"
                                            dir="ltr"
                                            required
                                            defaultValue={agreement.version}
                                            className="font-mono"
                                        />
                                        <InputError message={errors.version} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="title_ar">
                                            {t('agreement.admin.title_ar')}
                                        </Label>
                                        <Input
                                            id="title_ar"
                                            name="title_ar"
                                            dir="rtl"
                                            required
                                            defaultValue={agreement.title_ar}
                                        />
                                        <InputError message={errors.title_ar} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="title_en">
                                            {t('agreement.admin.title_en')}
                                        </Label>
                                        <Input
                                            id="title_en"
                                            name="title_en"
                                            dir="ltr"
                                            required
                                            defaultValue={agreement.title_en}
                                        />
                                        <InputError message={errors.title_en} />
                                    </div>
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="body_ar">
                                            {t('agreement.admin.body_ar')}
                                        </Label>
                                        <Textarea
                                            id="body_ar"
                                            name="body_ar"
                                            dir="rtl"
                                            required
                                            rows={16}
                                            defaultValue={agreement.body_ar}
                                        />
                                        <InputError message={errors.body_ar} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="body_en">
                                            {t('agreement.admin.body_en')}
                                        </Label>
                                        <Textarea
                                            id="body_en"
                                            name="body_en"
                                            dir="ltr"
                                            required
                                            rows={16}
                                            defaultValue={agreement.body_en}
                                        />
                                        <InputError message={errors.body_en} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="change_note_ar">
                                            {t(
                                                'agreement.admin.change_note_ar',
                                            )}
                                        </Label>
                                        <Textarea
                                            id="change_note_ar"
                                            name="change_note_ar"
                                            dir="rtl"
                                            rows={3}
                                            defaultValue={
                                                agreement.change_note_ar ?? ''
                                            }
                                        />
                                        <InputError
                                            message={errors.change_note_ar}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="change_note_en">
                                            {t(
                                                'agreement.admin.change_note_en',
                                            )}
                                        </Label>
                                        <Textarea
                                            id="change_note_en"
                                            name="change_note_en"
                                            dir="ltr"
                                            rows={3}
                                            defaultValue={
                                                agreement.change_note_en ?? ''
                                            }
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            {t(
                                                'agreement.admin.change_note_hint',
                                            )}
                                        </p>
                                        <InputError
                                            message={errors.change_note_en}
                                        />
                                    </div>
                                </div>

                                <div className="flex flex-wrap items-center gap-3">
                                    <Button type="submit" disabled={processing}>
                                        {processing ? <Spinner /> : <Save />}
                                        {t('agreement.admin.save')}
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                ) : (
                    <section className="space-y-4">
                        <p className="flex items-center gap-2 rounded-xl border bg-muted/40 p-3 text-sm text-muted-foreground">
                            <Lock
                                className="size-4 shrink-0"
                                aria-hidden="true"
                            />
                            {t('agreement.admin.immutable')}
                            {agreement.content_hash && (
                                <span
                                    dir="ltr"
                                    className="ms-auto font-mono text-xs"
                                >
                                    {agreement.content_hash.slice(0, 16)}
                                </span>
                            )}
                        </p>

                        <div className="grid gap-4 lg:grid-cols-2">
                            <article
                                dir="rtl"
                                className="rounded-xl border bg-card p-5 text-sm leading-relaxed whitespace-pre-line"
                            >
                                <h2 className="mb-3 text-base font-extrabold">
                                    {agreement.title_ar}
                                </h2>
                                {agreement.body_ar}
                            </article>
                            <article
                                dir="ltr"
                                className="rounded-xl border bg-card p-5 text-sm leading-relaxed whitespace-pre-line"
                            >
                                <h2 className="mb-3 text-base font-extrabold">
                                    {agreement.title_en}
                                </h2>
                                {agreement.body_en}
                            </article>
                        </div>
                    </section>
                )}

                {draft && (
                    <div className="flex flex-wrap items-center gap-3">
                        {/* Publishing is the one irreversible act on this
                            screen, so it is asked about in so many words. */}
                        <Dialog
                            open={confirmPublish}
                            onOpenChange={setConfirmPublish}
                        >
                            <DialogTrigger asChild>
                                <Button type="button">
                                    <Send />
                                    {t('agreement.admin.publish')}
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogTitle>
                                    {t('agreement.admin.publish')}{' '}
                                    {agreement.version}
                                </DialogTitle>
                                <DialogDescription>
                                    {t('agreement.admin.publish_confirm', {
                                        version: agreement.version,
                                    })}
                                </DialogDescription>
                                <Form
                                    action={`/admin/agreements/${agreement.id}/publish`}
                                    method="post"
                                    onSuccess={() => setConfirmPublish(false)}
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
                                                {t('agreement.admin.publish')}
                                            </Button>
                                        </DialogFooter>
                                    )}
                                </Form>
                            </DialogContent>
                        </Dialog>

                        <Dialog
                            open={confirmDelete}
                            onOpenChange={setConfirmDelete}
                        >
                            <DialogTrigger asChild>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    className="text-destructive hover:text-destructive"
                                >
                                    <Trash2 />
                                    {t('agreement.admin.delete_draft')}
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogTitle>
                                    {t('agreement.admin.delete_draft')}
                                </DialogTitle>
                                <DialogDescription>
                                    {t('agreement.admin.delete_confirm', {
                                        version: agreement.version,
                                    })}
                                </DialogDescription>
                                <Form
                                    action={`/admin/agreements/${agreement.id}`}
                                    method="delete"
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
                                                variant="destructive"
                                                disabled={processing}
                                            >
                                                {t(
                                                    'agreement.admin.delete_draft',
                                                )}
                                            </Button>
                                        </DialogFooter>
                                    )}
                                </Form>
                            </DialogContent>
                        </Dialog>
                    </div>
                )}

                {!draft && (
                    <section className="space-y-3">
                        <h2 className="text-sm font-extrabold">
                            {t('agreement.admin.acceptances_title')}{' '}
                            <span className="text-muted-foreground tabular-nums">
                                ({acceptances.length})
                            </span>
                        </h2>

                        {acceptances.length === 0 ? (
                            <p className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                                {t('agreement.admin.none_accepted')}
                            </p>
                        ) : (
                            <div className="overflow-x-auto rounded-xl border">
                                <table className="w-full text-sm">
                                    <thead className="bg-muted/50 text-start text-xs text-muted-foreground">
                                        <tr>
                                            <th className="p-3 text-start font-medium">
                                                {t('agreement.admin.col_venue')}
                                            </th>
                                            <th className="p-3 text-start font-medium">
                                                {t(
                                                    'agreement.admin.col_signed',
                                                )}
                                            </th>
                                            <th className="p-3 text-start font-medium">
                                                {t('agreement.admin.col_phone')}
                                            </th>
                                            <th className="p-3 text-start font-medium">
                                                {t('agreement.admin.col_date')}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {acceptances.map((acceptance) => (
                                            <tr key={acceptance.id}>
                                                <td className="p-3 align-top">
                                                    <p className="font-medium">
                                                        {localised(
                                                            locale,
                                                            acceptance.place_ar,
                                                            acceptance.place_en,
                                                        )}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {acceptance.legal_name}
                                                    </p>
                                                </td>
                                                <td className="p-3 align-top">
                                                    <p>
                                                        {
                                                            acceptance.representative_name
                                                        }
                                                        {acceptance.representative_title &&
                                                            ` · ${acceptance.representative_title}`}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {acceptance.accepted_by}
                                                    </p>
                                                </td>
                                                <td
                                                    className="p-3 text-start align-top"
                                                    dir="ltr"
                                                >
                                                    <p>
                                                        {
                                                            acceptance.representative_phone
                                                        }
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {acceptance.otp_channel ===
                                                        'none'
                                                            ? t(
                                                                  'agreement.otp_none',
                                                              )
                                                            : t(
                                                                  'agreement.otp_via',
                                                                  {
                                                                      channel:
                                                                          acceptance.otp_channel,
                                                                  },
                                                              )}
                                                    </p>
                                                </td>
                                                <td className="p-3 align-top text-muted-foreground tabular-nums">
                                                    {formatDate(
                                                        acceptance.accepted_at,
                                                    )}
                                                    {acceptance.ip && (
                                                        <p
                                                            dir="ltr"
                                                            className="text-start font-mono text-xs"
                                                        >
                                                            {acceptance.ip}
                                                        </p>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </section>
                )}
            </div>
        </>
    );
}

AdminAgreement.layout = {
    breadcrumbs: [
        { title: 'agreement.admin.title', href: '/admin/agreements' },
        { title: 'agreement.title', href: '#' },
    ],
};
