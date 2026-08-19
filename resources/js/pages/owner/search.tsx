import { Form, Head, Link } from '@inertiajs/react';
import { Search, Users } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import Heading from '@/components/heading';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { localised, useLocale } from '@/lib/locale';
import { useTranslation } from '@/lib/translation';
import { search } from '@/routes/owner';
import type { TicketStatus } from '@/types/public';

type Result = {
    token: string;
    full_name: string;
    phone: string;
    quantity: number;
    arrived_quantity: number;
    status: TicketStatus;
    event_title_ar: string;
    event_title_en: string;
};

type Props = { q: string; results: Result[] };

export default function TicketSearch({ q, results }: Props) {
    const { locale } = useLocale();
    const t = useTranslation();

    return (
        <>
            <Head title={t('owner.search_all')} />

            <div className="mx-auto w-full max-w-2xl space-y-6 p-4">
                <Heading
                    variant="small"
                    title={t('owner.search_all')}
                    description={t('owner.search_hint')}
                />

                <Form
                    action={search().url}
                    method="get"
                    className="flex items-end gap-2"
                >
                    <div className="grid flex-1 gap-2">
                        <Label htmlFor="q">{t('common.search')}</Label>
                        <Input id="q" name="q" defaultValue={q} autoFocus />
                    </div>
                    <Button
                        type="submit"
                        aria-label={t('common.search')}
                        className="cursor-pointer"
                    >
                        <Search />
                    </Button>
                </Form>

                {q.length > 0 && q.length < 3 && (
                    <p className="text-sm text-muted-foreground">
                        {t('owner.search_short')}
                    </p>
                )}

                {q.length >= 3 && results.length === 0 && (
                    <EmptyState icon={Search} title={t('owner.no_results')} />
                )}

                <ul className="space-y-2">
                    {results.map((result) => (
                        <li key={result.token}>
                            <Link
                                href={`/verify/${result.token}`}
                                className="flex cursor-pointer items-center justify-between gap-4 rounded-xl border p-4 transition-colors duration-200 hover:border-primary/40 hover:bg-muted/50"
                            >
                                <span className="min-w-0">
                                    <span className="block truncate font-medium">
                                        {result.full_name}
                                    </span>
                                    <span className="block truncate text-xs text-muted-foreground">
                                        {localised(
                                            locale,
                                            result.event_title_ar,
                                            result.event_title_en,
                                        )}
                                    </span>
                                    <span
                                        className="block font-mono text-xs text-muted-foreground"
                                        dir="ltr"
                                    >
                                        {result.phone}
                                    </span>
                                </span>

                                <span className="flex shrink-0 items-center gap-3 text-sm">
                                    <span className="inline-flex items-center gap-1.5">
                                        <Users className="size-4" />
                                        {result.quantity}
                                    </span>
                                    <StatusBadge status={result.status} />
                                </span>
                            </Link>
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}

TicketSearch.layout = {
    breadcrumbs: [{ title: 'owner.search_all', href: search() }],
};
