import { Head } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import EventController from '@/actions/App/Http/Controllers/Owner/EventController';
import { BackLink } from '@/components/back-link';
import { Button } from '@/components/ui/button';
import { dateTag } from '@/lib/format';
import { localised, useLocale } from '@/lib/locale';
import { useTranslation } from '@/lib/translation';

type Row = {
    reference: string;
    full_name: string;
    phone: string;
    quantity: number;
    arrived_quantity: number;
    status: string;
    amount_due: number;
};

type Props = {
    event: {
        title_ar: string;
        title_en: string;
        starts_at: string;
        total_quantity: number;
        price: number;
        currency: string;
        is_free: boolean;
    };
    place: { name_ar: string; name_en: string };
    summary: {
        bookings: number;
        seats: number;
        paid_seats: number;
        outstanding_seats: number;
    };
    rows: Row[];
};

export default function DoorSheet({ event, place, summary, rows }: Props) {
    const { locale } = useLocale();
    const t = useTranslation();
    const dateLocale = dateTag(locale);

    const title = localised(locale, event.title_ar, event.title_en);
    const placeName = localised(locale, place.name_ar, place.name_en);

    return (
        <div className="mx-auto w-full max-w-4xl space-y-6 p-4 print:max-w-none print:p-0">
            <Head title={t('owner.door_sheet')} />

            {/* Screen-only controls; the printed page starts at the header. */}
            <div className="flex flex-wrap items-center justify-between gap-3 print:hidden">
                <div>
                    <BackLink
                        href={EventController.index().url}
                        label="common.back_to_events"
                    />
                    <p className="text-sm text-muted-foreground">
                        {t('owner.door_sheet_hint')}
                    </p>
                </div>
                <Button
                    type="button"
                    onClick={() => window.print()}
                    className="cursor-pointer"
                >
                    <Printer />
                    {t('owner.print')}
                </Button>
            </div>

            <header className="space-y-1 border-b pb-4">
                <h1 className="text-xl font-bold">{title}</h1>
                <p className="text-sm text-muted-foreground">
                    {placeName} ·{' '}
                    {new Date(event.starts_at).toLocaleString(dateLocale, {
                        dateStyle: 'full',
                        timeStyle: 'short',
                    })}
                </p>
            </header>

            <section className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                {[
                    [t('owner.bookings'), summary.bookings],
                    [t('owner.seats'), summary.seats],
                    [t('owner.paid_seats'), summary.paid_seats],
                    [t('owner.outstanding'), summary.outstanding_seats],
                ].map(([label, value]) => (
                    <div key={String(label)} className="rounded-lg border p-3">
                        <p className="text-xs text-muted-foreground">{label}</p>
                        <p className="text-xl font-bold tabular-nums">
                            {value}
                        </p>
                    </div>
                ))}
            </section>

            <div className="overflow-x-auto">
                <table className="w-full border-collapse text-sm">
                    <thead>
                        <tr className="border-b text-start">
                            <th className="p-2 text-start font-medium">
                                {t('owner.col_name')}
                            </th>
                            <th className="p-2 text-start font-medium">
                                {t('owner.col_phone')}
                            </th>
                            <th className="p-2 text-start font-medium">
                                {t('owner.col_ref')}
                            </th>
                            <th className="p-2 text-center font-medium">
                                {t('owner.col_seats')}
                            </th>
                            <th className="p-2 text-center font-medium">
                                {t('owner.col_due')}
                            </th>
                            {/* Left blank on purpose: the door writes in it. */}
                            <th className="p-2 text-center font-medium">
                                {t('owner.col_arrived')}
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        {rows.map((row) => (
                            <tr
                                key={row.reference}
                                className="border-b last:border-0"
                            >
                                <td className="p-2 font-medium">
                                    {row.full_name}
                                </td>
                                <td className="p-2 font-mono text-xs" dir="ltr">
                                    {row.phone}
                                </td>
                                <td className="p-2 font-mono text-xs" dir="ltr">
                                    {row.reference}
                                </td>
                                <td className="p-2 text-center tabular-nums">
                                    {row.quantity}
                                </td>
                                <td className="p-2 text-center tabular-nums">
                                    {row.amount_due > 0
                                        ? `${row.amount_due.toLocaleString('en-GB')} ${event.currency}`
                                        : '—'}
                                </td>
                                <td className="p-2 text-center">
                                    <span className="inline-block h-5 w-14 border-b border-dashed align-middle" />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
