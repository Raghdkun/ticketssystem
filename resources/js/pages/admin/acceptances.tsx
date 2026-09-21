import { Head, Link, router } from '@inertiajs/react';
import { ClipboardList, Search } from 'lucide-react';
import { useState } from 'react';
import { SELECT_CLASS } from '@/components/commercial/offer-fields';
import { EmptyState } from '@/components/empty-state';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dateTag } from '@/lib/format';
import { localised, useLocale } from '@/lib/locale';
import { useTranslation } from '@/lib/translation';

type Row = {
    kind: 'terms' | 'offer' | 'order';
    id: number;
    title_ar: string;
    title_en: string;
    reference: string;
    place_ar: string;
    place_en: string;
    place_slug: string;
    representative: string | null;
    role: string | null;
    phone: string | null;
    by: string | null;
    method: string | null;
    otp: string;
    ip: string | null;
    hash: string | null;
    at: string | null;
    href: string;
};

type Filter = {
    q: string;
    kind: string;
    from: string | null;
    to: string | null;
};

/**
 * Every acceptance on the platform, narrowed by venue, kind and date.
 * Read only: the rows link to the record; nothing here edits one.
 */
export default function AdminAcceptances({
    rows,
    filter,
}: {
    rows: Row[];
    filter: Filter;
}) {
    const t = useTranslation();
    const { locale } = useLocale();
    const dateLocale = dateTag(locale);
    const [form, setForm] = useState({
        q: filter.q,
        kind: filter.kind,
        from: filter.from ?? '',
        to: filter.to ?? '',
    });

    const apply = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/admin/acceptances',
            Object.fromEntries(
                Object.entries(form).filter(([, v]) => v !== ''),
            ),
            { preserveState: true },
        );
    };

    return (
        <>
            <Head title={t('audit.title')} />

            <div className="space-y-6 p-4">
                <Heading
                    variant="small"
                    title={t('audit.title')}
                    description={t('audit.subtitle')}
                />

                <form
                    onSubmit={apply}
                    className="grid gap-3 rounded-xl border p-4 sm:grid-cols-5 sm:items-end"
                >
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="q">{t('audit.filter_venue')}</Label>
                        <Input
                            id="q"
                            value={form.q}
                            onChange={(e) =>
                                setForm({ ...form, q: e.target.value })
                            }
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="kind">{t('audit.filter_kind')}</Label>
                        <select
                            id="kind"
                            value={form.kind}
                            onChange={(e) =>
                                setForm({ ...form, kind: e.target.value })
                            }
                            className={SELECT_CLASS}
                        >
                            <option value="">{t('audit.all')}</option>
                            {(['terms', 'offer', 'order'] as const).map(
                                (kind) => (
                                    <option key={kind} value={kind}>
                                        {t(`documents.kind.${kind}`)}
                                    </option>
                                ),
                            )}
                        </select>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="from">{t('audit.from')}</Label>
                        <Input
                            id="from"
                            type="date"
                            value={form.from}
                            onChange={(e) =>
                                setForm({ ...form, from: e.target.value })
                            }
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="to">{t('audit.to')}</Label>
                        <Input
                            id="to"
                            type="date"
                            value={form.to}
                            onChange={(e) =>
                                setForm({ ...form, to: e.target.value })
                            }
                        />
                    </div>
                    <div className="sm:col-span-5">
                        <Button type="submit" variant="outline">
                            <Search />
                            {t('audit.apply')}
                        </Button>
                    </div>
                </form>

                {rows.length === 0 ? (
                    <EmptyState icon={ClipboardList} title={t('audit.none')} />
                ) : (
                    <div className="overflow-x-auto rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-xs text-muted-foreground">
                                <tr>
                                    <th className="p-3 text-start font-medium">
                                        {t('documents.col_document')}
                                    </th>
                                    <th className="p-3 text-start font-medium">
                                        {t('agreement.admin.col_venue')}
                                    </th>
                                    <th className="p-3 text-start font-medium">
                                        {t('agreement.admin.col_signed')}
                                    </th>
                                    <th className="p-3 text-start font-medium">
                                        {t('agreement.admin.col_method')}
                                    </th>
                                    <th className="p-3 text-start font-medium">
                                        {t('agreement.admin.col_date')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {rows.map((row) => (
                                    <tr key={`${row.kind}-${row.id}`}>
                                        <td className="p-3 align-top">
                                            <Link
                                                href={row.href}
                                                className="flex items-center gap-2 font-medium underline-offset-4 hover:underline"
                                            >
                                                {localised(
                                                    locale,
                                                    row.title_ar,
                                                    row.title_en,
                                                )}
                                                <Badge variant="outline">
                                                    {t(
                                                        `documents.kind.${row.kind}`,
                                                    )}
                                                </Badge>
                                            </Link>
                                            <p className="font-mono text-xs text-muted-foreground">
                                                {row.reference}
                                                {row.hash &&
                                                    ` · ${row.hash.slice(0, 12)}`}
                                            </p>
                                        </td>
                                        <td className="p-3 align-top">
                                            {localised(
                                                locale,
                                                row.place_ar,
                                                row.place_en,
                                            )}
                                        </td>
                                        <td className="p-3 align-top">
                                            <p>
                                                {row.representative}
                                                {row.role &&
                                                    ` · ${t(`agreement.roles.${row.role}`)}`}
                                            </p>
                                            <p
                                                dir="ltr"
                                                className="text-start text-xs text-muted-foreground"
                                            >
                                                {row.phone}
                                                {row.by && ` · ${row.by}`}
                                            </p>
                                        </td>
                                        <td className="p-3 align-top text-muted-foreground">
                                            <p>
                                                {row.method &&
                                                    t(
                                                        `agreement.method.${row.method}`,
                                                    )}
                                            </p>
                                            {row.ip && (
                                                <p
                                                    dir="ltr"
                                                    className="text-start font-mono text-xs"
                                                >
                                                    {row.ip}
                                                </p>
                                            )}
                                        </td>
                                        <td className="p-3 align-top text-muted-foreground tabular-nums">
                                            {row.at &&
                                                new Date(row.at).toLocaleString(
                                                    dateLocale,
                                                    {
                                                        dateStyle: 'medium',
                                                        timeStyle: 'short',
                                                    },
                                                )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}

AdminAcceptances.layout = {
    breadcrumbs: [
        { title: 'agreement.admin.title', href: '/admin/agreements' },
        { title: 'audit.title', href: '/admin/acceptances' },
    ],
};
