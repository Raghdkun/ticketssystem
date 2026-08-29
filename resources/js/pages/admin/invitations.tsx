import { Form, Head, usePage } from '@inertiajs/react';
import { Check, Copy, Mail, Trash2, UserPlus } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { useClipboard } from '@/hooks/use-clipboard';
import { dateTag } from '@/lib/format';
import { useLocale } from '@/lib/locale';
import { useTranslation } from '@/lib/translation';
import { invitations as invitationsRoute } from '@/routes/admin/index';

type Invitation = {
    id: number;
    email: string;
    requires_approval: boolean;
    expires_at: string;
    accepted_at: string | null;
    is_open: boolean;
    invited_by: string | null;
};

export default function AdminInvitations({
    invitations,
}: {
    invitations: Invitation[];
}) {
    const t = useTranslation();
    const { locale } = useLocale();
    const dateLocale = dateTag(locale);
    const [copied, copy] = useClipboard();

    // Flashed by the server exactly once. Only the hash is stored, so this is
    // the single moment the link is knowable.
    const link = usePage<{ flash?: { invitation_link?: string } }>().props.flash
        ?.invitation_link;

    return (
        <>
            <Head title={t('invite.admin_title')} />

            <div className="space-y-6 p-4">
                <Heading
                    variant="small"
                    title={t('invite.admin_title')}
                    description={t('invite.admin_subtitle')}
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
                    action="/admin/invitations"
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
                                    placeholder="owner@example.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <label className="flex min-h-11 items-center gap-3 text-sm">
                                <Switch name="requires_approval" value="1" />
                                {t('roles.needs_approval')}
                            </label>
                            <input
                                type="hidden"
                                name="requires_approval"
                                value="0"
                            />

                            <Button
                                type="submit"
                                disabled={processing}
                                className="cursor-pointer"
                            >
                                {processing ? <Spinner /> : <UserPlus />}
                                {t('invite.send')}
                            </Button>
                        </>
                    )}
                </Form>

                {invitations.length === 0 ? (
                    <EmptyState icon={Mail} title={t('invite.none')} />
                ) : (
                    <ul className="divide-y rounded-xl border">
                        {invitations.map((invitation) => (
                            <li
                                key={invitation.id}
                                className="flex flex-wrap items-center justify-between gap-3 p-4"
                            >
                                <div className="min-w-0">
                                    <p
                                        className="flex items-center gap-2 truncate font-medium"
                                        dir="ltr"
                                    >
                                        {invitation.email}
                                        {invitation.accepted_at ? (
                                            <Badge>
                                                {t('invite.accepted')}
                                            </Badge>
                                        ) : invitation.is_open ? (
                                            <Badge variant="secondary">
                                                {t('invite.pending')}
                                            </Badge>
                                        ) : (
                                            <Badge variant="outline">
                                                {t('invite.expired')}
                                            </Badge>
                                        )}
                                        {invitation.requires_approval && (
                                            <Badge variant="outline">
                                                {t('roles.needs_approval')}
                                            </Badge>
                                        )}
                                    </p>
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        {t('invite.expires', {
                                            date: new Date(
                                                invitation.expires_at,
                                            ).toLocaleDateString(dateLocale, {
                                                dateStyle: 'medium',
                                            }),
                                        })}
                                    </p>
                                </div>

                                {!invitation.accepted_at && (
                                    <Form
                                        action={`/admin/invitations/${invitation.id}`}
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
                                                {t('invite.revoke')}
                                            </Button>
                                        )}
                                    </Form>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

AdminInvitations.layout = {
    breadcrumbs: [{ title: 'invite.admin_title', href: invitationsRoute() }],
};
