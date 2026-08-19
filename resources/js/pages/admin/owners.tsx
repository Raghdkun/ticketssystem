import { Form, Head } from '@inertiajs/react';
import {
    CalendarDays,
    ShieldBan,
    ShieldCheck,
    Ticket as TicketIcon,
    UserPlus,
} from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Counter } from '@/components/motion/counter';
import { Stagger, StaggerItem } from '@/components/motion/stagger';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    revenue: number;
};

type Props = { stats: Stats; owners: Owner[] };

function Stat({ label, value }: { label: string; value: string | number }) {
    return (
        <StaggerItem className="rounded-xl border p-4 transition-colors hover:border-primary/40">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 text-2xl font-bold tabular-nums">
                {typeof value === 'number' ? <Counter value={value} /> : value}
            </p>
        </StaggerItem>
    );
}

export default function AdminOwners({ stats, owners: rows }: Props) {
    const { locale } = useLocale();
    const t = useTranslation();

    return (
        <>
            <Head title={t('admin.title')} />

            <div className="space-y-8 p-4">
                <Heading
                    variant="small"
                    title={t('admin.title')}
                    description={t('admin.subtitle')}
                />

                <Stagger className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <Stat label={t('admin.stat_owners')} value={stats.owners} />
                    <Stat label={t('admin.stat_events')} value={stats.events} />
                    <Stat
                        label={t('admin.stat_tickets')}
                        value={stats.tickets}
                    />
                    <Stat
                        label={t('admin.stat_paid')}
                        value={stats.paid_tickets}
                    />
                    <Stat
                        label={t('admin.stat_pending')}
                        value={stats.pending_tickets}
                    />
                    <Stat
                        label={t('admin.stat_seats')}
                        value={stats.seats_paid}
                    />
                    <Stat
                        label={t('admin.stat_revenue')}
                        value={stats.revenue.toLocaleString()}
                    />
                    <Stat label={t('admin.suspended')} value={stats.banned} />
                </Stagger>

                <Collapsible className="rounded-xl border">
                    <CollapsibleTrigger asChild>
                        <button
                            type="button"
                            className="flex w-full cursor-pointer items-center gap-2 p-4 text-sm font-medium transition-colors hover:bg-muted/50"
                        >
                            <UserPlus className="size-4" />
                            {t('admin.new_owner')}
                        </button>
                    </CollapsibleTrigger>

                    <CollapsibleContent className="border-t p-4">
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
                    <p className="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground">
                        {t('admin.no_owners')}
                    </p>
                ) : (
                    <ul className="space-y-3">
                        {rows.map((owner) => (
                            <li
                                key={owner.id}
                                className="flex flex-wrap items-center justify-between gap-4 rounded-xl border p-4 transition-colors duration-200 hover:border-primary/40"
                            >
                                <div className="min-w-0">
                                    <div className="flex items-center gap-2">
                                        <p className="font-medium">
                                            {owner.name}
                                        </p>
                                        {owner.banned && (
                                            <Badge variant="destructive">
                                                {t('admin.suspended')}
                                            </Badge>
                                        )}
                                    </div>
                                    <p
                                        className="text-xs text-muted-foreground"
                                        dir="ltr"
                                    >
                                        {owner.email}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {owner.places
                                            .map((place) =>
                                                localised(
                                                    locale,
                                                    place.name_ar,
                                                    place.name_en,
                                                ),
                                            )
                                            .join(' · ')}
                                    </p>
                                </div>

                                <div className="flex items-center gap-4 text-sm">
                                    <span
                                        className="inline-flex items-center gap-1.5 text-muted-foreground"
                                        title={t('admin.stat_events')}
                                    >
                                        <CalendarDays className="size-4" />
                                        {owner.events_count}
                                    </span>

                                    <span
                                        className="inline-flex items-center gap-1.5 text-muted-foreground"
                                        title={t('admin.stat_tickets')}
                                    >
                                        <TicketIcon className="size-4" />
                                        {owner.tickets_count}
                                    </span>

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
                                                        : 'destructive'
                                                }
                                                disabled={processing}
                                            >
                                                {owner.banned ? (
                                                    <ShieldCheck />
                                                ) : (
                                                    <ShieldBan />
                                                )}
                                                {owner.banned
                                                    ? t('admin.unban')
                                                    : t('admin.ban')}
                                            </Button>
                                        )}
                                    </Form>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

AdminOwners.layout = {
    breadcrumbs: [{ title: 'admin.title', href: owners() }],
};
