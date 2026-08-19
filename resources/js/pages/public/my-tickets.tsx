import { Form, Head, Link } from '@inertiajs/react';
import { Search, Ticket as TicketIcon, Users } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { FlashToaster } from '@/components/flash-toaster';
import { LanguageToggle } from '@/components/language-toggle';
import { PublicFooter } from '@/components/public-footer';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { localised, useLocale } from '@/lib/locale';
import { useStoredTickets } from '@/lib/tickets';
import { useTranslation } from '@/lib/translation';

type LookupResult = {
    masked_name: string;
    quantity: number;
    status: string;
    created_at: string | null;
    event_title_ar: string;
    event_title_en: string;
    place_name_ar: string;
    place_name_en: string;
    whatsapp_number: string | null;
};

type Props = { phone: string; searched: boolean; results: LookupResult[] };

export default function MyTicketsPage({ phone, searched, results }: Props) {
    const { locale } = useLocale();
    const t = useTranslation();
    const saved = useStoredTickets();

    return (
        <div className="min-h-dvh bg-background">
            <Head title={t('ticket.my_tickets')} />

            <FlashToaster />

            <main
                id="main-content"
                className="mx-auto w-full max-w-md space-y-8 p-5"
            >
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">
                        {t('ticket.my_tickets')}
                    </h1>
                    <LanguageToggle className="bg-black/10 text-foreground dark:bg-white/10" />
                </div>

                {saved.length > 0 && (
                    <section className="space-y-3">
                        <h2 className="text-sm font-medium text-muted-foreground">
                            {t('ticket.saved_on_device')}
                        </h2>

                        <ul className="space-y-2">
                            {saved.map((ticket) => (
                                <li key={ticket.token}>
                                    <Link
                                        href={`/t/${ticket.token}`}
                                        className="flex items-center gap-3 rounded-xl border p-3 transition hover:bg-muted/50"
                                    >
                                        <TicketIcon className="size-4 shrink-0 text-muted-foreground" />
                                        <span className="min-w-0 flex-1 truncate text-sm font-medium">
                                            {ticket.title}
                                        </span>
                                        <span
                                            className="shrink-0 font-mono text-xs text-muted-foreground"
                                            dir="ltr"
                                        >
                                            {ticket.token
                                                .slice(0, 8)
                                                .toUpperCase()}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                <section className="space-y-4 rounded-xl border p-5">
                    <div>
                        <h2 className="text-lg font-semibold">
                            {t('ticket.lookup_title')}
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t('ticket.lookup_hint')}
                        </p>
                    </div>

                    <Form
                        action="/my-tickets"
                        method="get"
                        className="flex items-end gap-2"
                    >
                        <div className="grid flex-1 gap-2">
                            <Label htmlFor="phone">{t('event.mobile')}</Label>
                            <Input
                                id="phone"
                                name="phone"
                                type="tel"
                                dir="ltr"
                                inputMode="tel"
                                defaultValue={phone}
                                placeholder="09XXXXXXXX"
                            />
                        </div>
                        <Button
                            type="submit"
                            aria-label={t('common.search')}
                            className="cursor-pointer"
                        >
                            <Search />
                        </Button>
                    </Form>

                    {searched && results.length === 0 && (
                        <EmptyState
                            icon={Search}
                            title={t('ticket.lookup_empty')}
                        />
                    )}

                    <ul className="space-y-2">
                        {results.map((result, index) => (
                            <li
                                key={index}
                                className="rounded-xl border p-3 text-sm"
                            >
                                <p className="font-medium">
                                    {localised(
                                        locale,
                                        result.event_title_ar,
                                        result.event_title_en,
                                    )}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {localised(
                                        locale,
                                        result.place_name_ar,
                                        result.place_name_en,
                                    )}
                                </p>
                                <div className="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                                    <span dir="ltr">{result.masked_name}</span>
                                    <span className="inline-flex items-center gap-1">
                                        <Users className="size-3.5" />
                                        {result.quantity}
                                    </span>
                                    <span className="font-medium">
                                        {t(`ticket.status.${result.status}`)}
                                    </span>
                                </div>
                            </li>
                        ))}
                    </ul>
                </section>
            </main>

            <PublicFooter />
        </div>
    );
}
