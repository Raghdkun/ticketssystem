import { Form, Head, usePage } from '@inertiajs/react';
import { Check, Copy, Store, Trash2, UserPlus, Users } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useClipboard } from '@/hooks/use-clipboard';
import { initials } from '@/lib/initials';
import { useLocale } from '@/lib/locale';
import { useTranslation } from '@/lib/translation';
import staffRoute from '@/routes/owner/staff';

type Member = { id: number; name: string; email: string; banned: boolean };

type Invitation = {
    id: number;
    email: string;
    is_open: boolean;
    accepted_at: string | null;
    expires_at: string;
};

type Props = { hasPlace: boolean; staff: Member[]; invitations: Invitation[] };

export default function OwnerStaff({ hasPlace, staff, invitations }: Props) {
    const t = useTranslation();
    const { locale } = useLocale();
    const dateLocale = locale === 'ar' ? 'ar-SY' : 'en-GB';
    const [copied, copy] = useClipboard();

    const link = usePage<{ flash?: { invitation_link?: string } }>().props.flash
        ?.invitation_link;

    if (!hasPlace) {
        return (
            <>
                <Head title={t('staff.title')} />
                <div className="p-4">
                    <EmptyState icon={Store} title={t('dash.no_place')} />
                </div>
            </>
        );
    }

    return (
        <>
            <Head title={t('staff.title')} />

            <div className="space-y-6 p-4">
                <Heading
                    variant="small"
                    title={t('staff.title')}
                    description={t('staff.subtitle')}
                />

                {link && (
                    <div className="space-y-3 rounded-xl border border-primary/40 bg-primary/5 p-4">
                        <p className="text-sm font-medium">
                            {t('invite.link_ready')}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {t('invite.link_once')}
                        </p>
                        <div className="flex flex-wrap gap-2">
                            <Input
                                readOnly
                                value={link}
                                dir="ltr"
                                onFocus={(event) =>
                                    event.currentTarget.select()
                                }
                                className="min-w-0 flex-1 font-mono text-xs"
                            />
                            <Button
                                type="button"
                                onClick={() => copy(link)}
                                className="cursor-pointer"
                            >
                                {copied === link ? <Check /> : <Copy />}
                                {copied === link
                                    ? t('share.copied')
                                    : t('share.copy_link')}
                            </Button>
                        </div>
                    </div>
                )}

                <Form
                    action="/owner/staff"
                    method="post"
                    options={{ preserveScroll: true }}
                    className="space-y-4 rounded-xl border p-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="email">{t('auth.email')}</Label>
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    dir="ltr"
                                    required
                                    placeholder="door@example.com"
                                />
                                <InputError message={errors.email} />
                                <p className="text-xs text-muted-foreground">
                                    {t('staff.can_do')}
                                </p>
                            </div>

                            <Button
                                type="submit"
                                disabled={processing}
                                className="cursor-pointer"
                            >
                                {processing ? <Spinner /> : <UserPlus />}
                                {t('staff.invite')}
                            </Button>
                        </>
                    )}
                </Form>

                {staff.length === 0 && invitations.length === 0 ? (
                    <EmptyState icon={Users} title={t('staff.none')} />
                ) : (
                    <ul className="divide-y rounded-xl border">
                        {staff.map((member) => (
                            <li
                                key={member.id}
                                className="flex flex-wrap items-center justify-between gap-3 p-4"
                            >
                                <div className="flex min-w-0 items-center gap-3">
                                    <span
                                        aria-hidden
                                        className="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary"
                                    >
                                        {initials(member.name)}
                                    </span>
                                    <div className="min-w-0">
                                        <p className="font-medium">
                                            {member.name}
                                        </p>
                                        <p
                                            className="truncate text-xs text-muted-foreground"
                                            dir="ltr"
                                        >
                                            {member.email}
                                        </p>
                                    </div>
                                </div>

                                <Form
                                    action={`/owner/staff/${member.id}`}
                                    method="delete"
                                    options={{ preserveScroll: true }}
                                >
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            size="sm"
                                            variant="ghost"
                                            disabled={processing}
                                            className="cursor-pointer text-destructive hover:text-destructive"
                                        >
                                            <Trash2 />
                                            {t('staff.remove')}
                                        </Button>
                                    )}
                                </Form>
                            </li>
                        ))}

                        {invitations
                            .filter((invitation) => !invitation.accepted_at)
                            .map((invitation) => (
                                <li
                                    key={`i-${invitation.id}`}
                                    className="flex flex-wrap items-center justify-between gap-3 p-4"
                                >
                                    <div className="min-w-0">
                                        <p
                                            className="flex items-center gap-2 truncate text-sm"
                                            dir="ltr"
                                        >
                                            {invitation.email}
                                            <Badge variant="secondary">
                                                {invitation.is_open
                                                    ? t('invite.pending')
                                                    : t('invite.expired')}
                                            </Badge>
                                        </p>
                                        <p className="mt-0.5 text-xs text-muted-foreground">
                                            {t('invite.expires', {
                                                date: new Date(
                                                    invitation.expires_at,
                                                ).toLocaleDateString(
                                                    dateLocale,
                                                    {
                                                        dateStyle: 'medium',
                                                    },
                                                ),
                                            })}
                                        </p>
                                    </div>
                                </li>
                            ))}
                    </ul>
                )}
            </div>
        </>
    );
}

OwnerStaff.layout = {
    breadcrumbs: [{ title: 'staff.title', href: staffRoute.index() }],
};
