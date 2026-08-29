import { Head, router } from '@inertiajs/react';
import { ShieldCheck, Store, UserCog } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Switch } from '@/components/ui/switch';
import { initials } from '@/lib/initials';
import { useTranslation } from '@/lib/translation';
import { roles } from '@/routes/admin/index';

type Person = {
    id: number;
    name: string;
    email: string;
    is_super_admin: boolean;
    requires_approval: boolean;
    places_count: number;
    banned: boolean;
};

type Props = { people: Person[]; adminCount: number };

export default function AdminRoles({ people, adminCount }: Props) {
    const t = useTranslation();

    const save = (person: Person, patch: Partial<Person>) =>
        router.patch(
            `/admin/roles/${person.id}`,
            {
                is_super_admin: patch.is_super_admin ?? person.is_super_admin,
                requires_approval:
                    patch.requires_approval ?? person.requires_approval,
            },
            { preserveScroll: true },
        );

    return (
        <>
            <Head title={t('roles.title')} />

            <div className="space-y-6 p-4">
                <Heading
                    variant="small"
                    title={t('roles.title')}
                    description={t('roles.subtitle')}
                />

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full min-w-[44rem] text-sm">
                        <thead>
                            <tr className="border-b text-xs text-muted-foreground">
                                <th className="px-4 py-3 text-start font-medium">
                                    {t('admin.col_owner')}
                                </th>
                                <th className="px-4 py-3 text-start font-medium">
                                    {t('roles.venues')}
                                </th>
                                <th className="px-4 py-3 text-start font-medium">
                                    {t('roles.admin')}
                                </th>
                                <th className="px-4 py-3 text-start font-medium">
                                    {t('roles.needs_approval')}
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            {people.map((person) => {
                                // The last administrator has no way back:
                                // registration is closed, so a platform with
                                // none left cannot appoint one.
                                const lastAdmin =
                                    person.is_super_admin && adminCount <= 1;

                                return (
                                    <tr
                                        key={person.id}
                                        className="border-b transition-colors last:border-0 hover:bg-muted/40"
                                    >
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-3">
                                                <span
                                                    aria-hidden
                                                    className="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary"
                                                >
                                                    {initials(person.name)}
                                                </span>
                                                <div className="min-w-0">
                                                    <p className="flex items-center gap-2 font-medium">
                                                        {person.name}
                                                        {person.banned && (
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
                                                        {person.email}
                                                    </p>
                                                </div>
                                            </div>
                                        </td>

                                        <td className="px-4 py-3 text-muted-foreground">
                                            <span className="inline-flex items-center gap-1.5">
                                                <Store
                                                    className="size-4"
                                                    aria-hidden
                                                />
                                                <span className="tabular-nums">
                                                    {person.places_count}
                                                </span>
                                            </span>
                                        </td>

                                        <td className="px-4 py-3">
                                            <label className="inline-flex min-h-11 items-center gap-2">
                                                <Switch
                                                    checked={
                                                        person.is_super_admin
                                                    }
                                                    disabled={lastAdmin}
                                                    onCheckedChange={(on) =>
                                                        save(person, {
                                                            is_super_admin: on,
                                                        })
                                                    }
                                                    aria-label={`${t('roles.admin')} — ${person.name}`}
                                                />
                                                {person.is_super_admin && (
                                                    <ShieldCheck
                                                        className="size-4 text-primary"
                                                        aria-hidden
                                                    />
                                                )}
                                            </label>
                                            {lastAdmin && (
                                                <p className="text-xs text-muted-foreground">
                                                    {t('roles.last_admin_hint')}
                                                </p>
                                            )}
                                        </td>

                                        <td className="px-4 py-3">
                                            <label className="inline-flex min-h-11 items-center gap-2">
                                                <Switch
                                                    checked={
                                                        person.requires_approval
                                                    }
                                                    onCheckedChange={(on) =>
                                                        save(person, {
                                                            requires_approval:
                                                                on,
                                                        })
                                                    }
                                                    aria-label={`${t('roles.needs_approval')} — ${person.name}`}
                                                />
                                                {person.requires_approval && (
                                                    <UserCog
                                                        className="size-4 text-muted-foreground"
                                                        aria-hidden
                                                    />
                                                )}
                                            </label>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

AdminRoles.layout = {
    breadcrumbs: [{ title: 'roles.title', href: roles() }],
};
