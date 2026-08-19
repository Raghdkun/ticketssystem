import { Form, Head, Link, router } from '@inertiajs/react';
import { Search, Users } from 'lucide-react';
import Heading from '@/components/heading';
import { QrScanner } from '@/components/qr-scanner';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/lib/translation';
import { scan } from '@/routes/owner';
import type { TicketStatus } from '@/types/public';

type Result = {
    token: string;
    full_name: string;
    phone: string;
    quantity: number;
    status: TicketStatus;
    event_title_en: string;
    event_title_ar: string;
};

type Props = { phone: string; results: Result[] };

export default function ScanPage({ phone, results }: Props) {
    const t = useTranslation();

    return (
        <>
            <Head title={t('owner.verify_title')} />

            <div className="mx-auto w-full max-w-xl space-y-6 p-4">
                <Heading
                    variant="small"
                    title={t('owner.verify_title')}
                    description={t('owner.verify_subtitle')}
                />

                <QrScanner
                    onToken={(token) => router.visit(`/verify/${token}`)}
                />

                <div className="flex items-center gap-3 text-xs text-muted-foreground">
                    <span className="h-px flex-1 bg-border" />
                    {t('common.search')}
                    <span className="h-px flex-1 bg-border" />
                </div>

                <Form
                    action={scan().url}
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
                            defaultValue={phone}
                            placeholder="09XXXXXXXX"
                        />
                    </div>
                    <Button type="submit">
                        <Search />
                        {t('common.search')}
                    </Button>
                </Form>

                {phone && results.length === 0 && (
                    <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                        {t('owner.no_results')}
                    </p>
                )}

                <ul className="space-y-3">
                    {results.map((result) => (
                        <li key={result.token}>
                            <Link
                                href={`/verify/${result.token}`}
                                className="flex items-center justify-between gap-4 rounded-xl border p-4 transition hover:bg-muted/50"
                            >
                                <div className="min-w-0">
                                    <p className="truncate font-medium">
                                        {result.full_name}
                                    </p>
                                    <p className="truncate text-xs text-muted-foreground">
                                        {result.event_title_en}
                                    </p>
                                </div>
                                <div className="flex shrink-0 items-center gap-3 text-sm">
                                    <span className="inline-flex items-center gap-1.5">
                                        <Users className="size-4" />
                                        {result.quantity}
                                    </span>
                                    <StatusBadge status={result.status} />
                                </div>
                            </Link>
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}

ScanPage.layout = {
    breadcrumbs: [{ title: 'owner.verify_title', href: scan() }],
};
