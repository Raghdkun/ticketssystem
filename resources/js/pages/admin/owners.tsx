import { Form, Head } from '@inertiajs/react';
import { ShieldBan, ShieldCheck, UserCog, UserPlus } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Counter } from '@/components/motion/counter';
import { Stagger, StaggerItem } from '@/components/motion/stagger';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent } from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { initials } from '@/lib/initials';
import { localised, useLocale } from '@/lib/locale';
import { useTranslation } from '@/lib/translation';
import { owners } from '@/routes/admin';

type Owner = {
    id: number;
    name: string;
    email: string;
    banned: boolean;
    banned_at: string | null;
    places: { name_ar: string; name_en: string; slug: string }[];
    events_count: number;
    tickets_count: number;
};

type Stats = {
    owners: number;
    banned: number;
    events: number;
    tickets: number;
    paid_tickets: number;
    pending_tickets: number;
    seats_paid: number;
};

type Props = { stats: Stats; owners: Owner[] };

function Stat({
    label,
    value,
    hint,
    tone,
}: {
    label: string;
    value: string | number;
    hint?: string;
    tone?: string;
}) {
    return (
        <StaggerItem className="brand-surface rounded-xl border p-4 transition-colors hover:border-primary/40">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className={`mt-1 text-2xl font-bold tabular-nums ${tone ?? ''}`}>
                {typeof value === 'number' ? <Counter value={value} /> : value}
            </p>
            {hint ? (
                <p className="mt-0.5 truncate text-xs text-muted-foreground">
                    {hint}
                </p>
            ) : null}
        </StaggerItem>
    );
}

export default function AdminOwners({ stats, owners: rows }: Props) {
    const [addingOwner, setAddingOwner] = useState(false);
    const { locale } = useLocale();
    const t = useTranslation();

    return (
        <>
            <Head title={t('admin.title')} />

            <div className="space-y-8 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        variant="small"
                        title={t('admin.title')}
                        description={t('admin.subtitle')}
                    />

                    <Button
                        onClick={() => setAddingOwner((open) => !open)}
                        aria-expanded={addingOwner}
                        aria-controls="new-owner-form"
                        className="cursor-pointer"
                    >
                        <UserPlus />
                        {t('admin.new_owner')}
                    </Button>
                </div>

                <Stagger className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <Stat
                        label={t('admin.stat_owners')}
                        value={stats.owners}
                        hint={t('admin.n_suspended', { n: stats.banned })}
                    />
                    <Stat
                        label={t('admin.stat_events')}
                        value={stats.events}
                        hint={t('admin.n_tickets', { n: stats.tickets })}
                    />
                    <Stat
                        label={t('admin.stat_pending')}
                        value={stats.pending_tickets}
                        hint={t('admin.awaiting_payment')}
                        tone="text-amber-600 dark:text-amber-400"
                    />
                </Stagger>

                <Collapsible open={addingOwner} onOpenChange={setAddingOwner}>
                    <CollapsibleContent
                        id="new-owner-form"
                        className="rounded-xl border p-4"
                    >
                        <p className="mb-4 text-xs text-muted-foreground">
                            {t('admin.form_hint')}
                        </p>

                        <Form
                            action="/admin/owners"
                            method="post"
                            className="grid gap-4 sm:grid-cols-2"
                            options={{ preserveScroll: true }}
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">
                                            {t('admin.owner_name')}
                                        </Label>
                                        <Input id="name" name="name" required />
                                        <InputError message={errors.name} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="email">
                                            {t('admin.owner_email')}
                                        </Label>
                                        <Input
                                            id="email"
                                            name="email"
                                            type="email"
                                            dir="ltr"
                                            required
                                        />
                                        <InputError message={errors.email} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="password">
                                            {t('admin.owner_password')}
                                        </Label>
                                        <Input
                                            id="password"
                                            name="password"
                                            type="password"
                                            dir="ltr"
                                            required
                                        />
                                        <InputError message={errors.password} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="password_confirmation">
                                            {t('admin.owner_password_confirm')}
                                        </Label>
                                        <Input
                                            id="password_confirmation"
                                            name="password_confirmation"
                                            type="password"
                                            dir="ltr"
                                            required
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="place_name_en">
                                            {t('admin.venue_en')}
                                        </Label>
                                        <Input
                                            id="place_name_en"
                                            name="place_name_en"
                                            dir="ltr"
                                            required
                                        />
                                        <InputError
                                            message={errors.place_name_en}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="place_name_ar">
                                            {t('admin.venue_ar')}
                                        </Label>
                                        <Input
                                            id="place_name_ar"
                                            name="place_name_ar"
                                            dir="rtl"
                                            required
                                        />
                                        <InputError
                                            message={errors.place_name_ar}
                                        />
                                    </div>

                                    <div className="grid gap-2 sm:col-span-2">
                                        <Label htmlFor="whatsapp_number">
                                            {t('admin.whatsapp')}
                                        </Label>
                                        <Input
                                            id="whatsapp_number"
                                            name="whatsapp_number"
                                            type="tel"
                                            dir="ltr"
                                            placeholder="09XXXXXXXX"
                                        />
                                        <InputError
                                            message={errors.whatsapp_number}
                                        />
                                    </div>

                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="cursor-pointer sm:col-span-2"
                                    >
                                        <UserPlus />
                                        {t('admin.create')}
                                    </Button>
                                </>
                            )}
                        </Form>
                    </CollapsibleContent>
                </Collapsible>

                {rows.length === 0 ? (
                    <EmptyState icon={UserPlus} title={t('admin.no_owners')} />
                ) : (
                    <div className="overflow-x-auto rounded-xl border">
                        <table className="w-full min-w-[46rem] text-sm">
                            <thead>
                                <tr className="border-b text-xs text-muted-foreground">
                                    <th className="px-4 py-3 text-start font-medium">
                                        {t('admin.col_owner')}
                                    </th>
                                    <th className="px-4 py-3 text-start font-medium">
                                        {t('admin.col_place')}
                                    </th>
                                    <th className="px-4 py-3 text-start font-medium">
                                        {t('admin.stat_events')}
                                    </th>
                                    <th className="px-4 py-3 text-start font-medium">
                                        {t('admin.stat_tickets')}
                                    </th>
                                    <th className="px-4 py-3">
                                        <span className="sr-only">
                                            {t('admin.col_actions')}
                                        </span>
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                {rows.map((owner) => (
                                    <tr
                                        key={owner.id}
                                        className="border-b transition-colors last:border-0 hover:bg-muted/40"
                                    >
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-3">
                                                <span
                                                    aria-hidden
                                                    className="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary"
                                                >
                                                    {initials(owner.name)}
                                                </span>
                                                <div className="min-w-0">
                                                    <p className="flex items-center gap-2 font-medium">
                                                        {owner.name}
                                                        {owner.banned && (
                                                            <Badge variant="destructive">
                                                                {t(
                                                                    'admin.suspended',
                                                                )}
                                                            </Badge>
                                                        )}
                                                    </p>
                                                    <p
                                                        className="truncate text-xs text-muted-foreground"
                                                        dir="ltr"
                                                    >
                                                        {owner.email}
                                                    </p>
                                                </div>
                                            </div>
                                        </td>

                                        <td className="px-4 py-3 text-muted-foreground">
                                            {owner.places
                                                .map((place) =>
                                                    localised(
                                                        locale,
                                                        place.name_ar,
                                                        place.name_en,
                                                    ),
                                                )
                                                .join(' · ') || '—'}
                                        </td>

                                        <td className="px-4 py-3 tabular-nums">
                                            {owner.events_count}
                                        </td>

                                        <td className="px-4 py-3 tabular-nums">
                                            {owner.tickets_count}
                                        </td>

                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-end gap-2">
                                                {/* An impersonate control that
                                                    renders disabled still reads
                                                    as an option; a suspended
                                                    owner simply has none. */}
                                                {!owner.banned && (
                                                    <Form
                                                        action={`/admin/owners/${owner.id}/impersonate`}
                                                        method="post"
                                                    >
                                                        {({ processing }) => (
                                                            <Button
                                                                type="submit"
                                                                size="sm"
                                                                variant="outline"
                                                                className="cursor-pointer"
                                                                disabled={
                                                                    processing
                                                                }
                                                            >
                                                                <UserCog />
                                                                {t(
                                                                    'admin.impersonate',
                                                                )}
                                                            </Button>
                                                        )}
                                                    </Form>
                                                )}

                                                <Form
                                                    action={
                                                        owner.banned
                                                            ? `/admin/owners/${owner.id}/unban`
                                                            : `/admin/owners/${owner.id}/ban`
                                                    }
                                                    method="post"
                                                >
                                                    {({ processing }) => (
                                                        <Button
                                                            type="submit"
                                                            size="sm"
                                                            variant={
                                                                owner.banned
                                                                    ? 'outline'
                                                                    : 'ghost'
                                                            }
                                                            className="cursor-pointer"
                                                            disabled={
                                                                processing
                                                            }
                                                        >
                                                            {owner.banned ? (
                                                                <ShieldCheck />
                                                            ) : (
                                                                <ShieldBan />
                                                            )}
                                                            {owner.banned
                                                                ? t(
                                                                      'admin.unban',
                                                                  )
                                                                : t(
                                                                      'admin.ban',
                                                                  )}
                                                        </Button>
                                                    )}
                                                </Form>
                                            </div>
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

AdminOwners.layout = {
    breadcrumbs: [{ title: 'admin.title', href: owners() }],
};
