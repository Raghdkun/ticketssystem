import { Form, Head, Link } from '@inertiajs/react';
import { FileSignature, Plus } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { dateTag } from '@/lib/format';
import { localised, useLocale } from '@/lib/locale';
import { useTranslation } from '@/lib/translation';

export type VersionSummary = {
    id: number;
    version: string;
    title_ar: string;
    title_en: string;
    status: 'draft' | 'published' | 'retired';
    published_at: string | null;
    retired_at: string | null;
    acceptances_count: number;
};

type Outstanding = {
    version: string;
    places: {
        slug: string;
        name_ar: string;
        name_en: string;
        owner: string | null;
    }[];
} | null;

const BADGE: Record<
    VersionSummary['status'],
    'default' | 'secondary' | 'outline'
> = {
    published: 'default',
    draft: 'secondary',
    retired: 'outline',
};

export function VersionStatusBadge({
    status,
}: {
    status: VersionSummary['status'];
}) {
    const t = useTranslation();

    return (
        <Badge variant={BADGE[status]}>
            {t(`agreement.admin.status.${status}`)}
        </Badge>
    );
}

/**
 * Every version of the Partner Terms, and who has still to accept the
 * one in force.
 */
export default function AdminAgreements({
    versions,
    outstanding,
}: {
    versions: VersionSummary[];
    outstanding: Outstanding;
}) {
    const t = useTranslation();
    const { locale } = useLocale();
    const dateLocale = dateTag(locale);
    const [drafting, setDrafting] = useState(false);

    return (
        <>
            <Head title={t('agreement.admin.title')} />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        variant="small"
                        title={t('agreement.admin.title')}
                        description={t('agreement.admin.subtitle')}
                    />
                    <Button
                        type="button"
                        onClick={() => setDrafting((open) => !open)}
                        aria-expanded={drafting}
                    >
                        <Plus />
                        {t('agreement.admin.new_draft')}
                    </Button>
                </div>

                {drafting && (
                    <Form
                        action="/admin/agreements"
                        method="post"
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
                                            placeholder="1.1"
                                            className="font-mono"
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            {t('agreement.admin.version_hint')}
                                        </p>
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
                                            defaultValue="شروط الشراكة"
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
                                            defaultValue="Partner Terms"
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
                                            rows={12}
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
                                            rows={12}
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

                                <Button type="submit" disabled={processing}>
                                    {processing ? (
                                        <Spinner />
                                    ) : (
                                        <FileSignature />
                                    )}
                                    {t('agreement.admin.create')}
                                </Button>
                            </>
                        )}
                    </Form>
                )}

                {versions.length === 0 ? (
                    <EmptyState
                        icon={FileSignature}
                        title={t('agreement.admin.no_versions')}
                    />
                ) : (
                    <ul className="divide-y rounded-xl border">
                        {versions.map((version) => (
                            <li key={version.id}>
                                <Link
                                    href={`/admin/agreements/${version.id}`}
                                    className="flex min-h-11 flex-wrap items-center justify-between gap-3 p-4 transition-colors hover:bg-muted/50"
                                >
                                    <div className="min-w-0">
                                        <p className="flex items-center gap-2 font-semibold">
                                            <span className="font-mono">
                                                {version.version}
                                            </span>
                                            <VersionStatusBadge
                                                status={version.status}
                                            />
                                        </p>
                                        <p className="mt-0.5 truncate text-sm text-muted-foreground">
                                            {localised(
                                                locale,
                                                version.title_ar,
                                                version.title_en,
                                            )}
                                            {version.published_at && (
                                                <>
                                                    {' · '}
                                                    {new Date(
                                                        version.published_at,
                                                    ).toLocaleDateString(
                                                        dateLocale,
                                                        { dateStyle: 'medium' },
                                                    )}
                                                </>
                                            )}
                                        </p>
                                    </div>
                                    <span className="text-sm text-muted-foreground tabular-nums">
                                        {t('agreement.admin.acceptances', {
                                            n: version.acceptances_count,
                                        })}
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}

                <section className="space-y-3 rounded-xl border p-4 sm:p-6">
                    {outstanding === null ? (
                        <p className="text-sm text-muted-foreground">
                            {t('agreement.admin.nothing_in_force')}
                        </p>
                    ) : (
                        <>
                            <h2 className="text-sm font-extrabold">
                                {t('agreement.admin.outstanding', {
                                    version: outstanding.version,
                                })}
                            </h2>
                            {outstanding.places.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('agreement.admin.outstanding_none')}
                                </p>
                            ) : (
                                <ul className="divide-y text-sm">
                                    {outstanding.places.map((place) => (
                                        <li
                                            key={place.slug}
                                            className="flex flex-wrap items-center justify-between gap-2 py-2"
                                        >
                                            <span className="font-medium">
                                                {localised(
                                                    locale,
                                                    place.name_ar,
                                                    place.name_en,
                                                )}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {place.owner}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </>
                    )}
                </section>
            </div>
        </>
    );
}

AdminAgreements.layout = {
    breadcrumbs: [
        { title: 'agreement.admin.title', href: '/admin/agreements' },
    ],
};
