import { Head, router } from '@inertiajs/react';
import { CalendarDays, Check, Trash2, X } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { localised, useLocale } from '@/lib/locale';
import { useTranslation } from '@/lib/translation';
import { events as eventsRoute } from '@/routes/admin/index';

type AdminEvent = {
    id: number;
    title_ar: string;
    title_en: string;
    status: string;
    starts_at: string;
    tickets_count: number;
    total_quantity: number;
    place: { name_ar: string; name_en: string };
};

type Props = { events: AdminEvent[]; pending: number };

/** Status as a dot plus a word, never colour alone. */
function StatusChip({ status, label }: { status: string; label: string }) {
    const tone =
        status === 'published'
            ? 'var(--brand-jade-500)'
            : status === 'pending_review'
              ? 'var(--brand-saffron-500)'
              : 'var(--brand-basalt-400)';

    return (
        <span className="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium">
            <span
                className="size-1.5 rounded-full"
                style={{ backgroundColor: tone }}
            />
            {label}
        </span>
    );
}

export default function AdminEvents({ events, pending }: Props) {
    const t = useTranslation();
    const { locale } = useLocale();
    const dateLocale = locale === 'ar' ? 'ar-SY' : 'en-GB';

    return (
        <>
            <Head title={t('review.title')} />

            <div className="space-y-6 p-4">
                <Heading
                    variant="small"
                    title={t('review.title')}
                    description={
                        pending > 0
                            ? t('review.pending_count', { n: pending })
                            : t('review.subtitle')
                    }
                />

                {events.length === 0 ? (
                    <EmptyState icon={CalendarDays} title={t('review.none')} />
                ) : (
                    <div className="overflow-x-auto rounded-xl border">
                        <table className="w-full min-w-[48rem] text-sm">
                            <thead>
                                <tr className="border-b text-xs text-muted-foreground">
                                    <th className="px-4 py-3 text-start font-medium">
                                        {t('owner.events')}
                                    </th>
                                    <th className="px-4 py-3 text-start font-medium">
                                        {t('admin.col_place')}
                                    </th>
                                    <th className="px-4 py-3 text-start font-medium">
                                        {t('form.status')}
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
                                {events.map((event) => (
                                    <tr
                                        key={event.id}
                                        className="border-b transition-colors last:border-0 hover:bg-muted/40"
                                    >
                                        <td className="px-4 py-3">
                                            <p className="font-medium">
                                                {localised(
                                                    locale,
                                                    event.title_ar,
                                                    event.title_en,
                                                )}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {new Date(
                                                    event.starts_at,
                                                ).toLocaleDateString(
                                                    dateLocale,
                                                    {
                                                        dateStyle: 'medium',
                                                    },
                                                )}
                                            </p>
                                        </td>

                                        <td className="px-4 py-3 text-muted-foreground">
                                            {localised(
                                                locale,
                                                event.place.name_ar,
                                                event.place.name_en,
                                            )}
                                        </td>

                                        <td className="px-4 py-3">
                                            <StatusChip
                                                status={event.status}
                                                label={t(
                                                    `event.status.${event.status}`,
                                                )}
                                            />
                                        </td>

                                        <td className="px-4 py-3 tabular-nums">
                                            {event.tickets_count}
                                        </td>

                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-end gap-2">
                                                {event.status ===
                                                    'pending_review' && (
                                                    <>
                                                        <Button
                                                            size="sm"
                                                            className="cursor-pointer"
                                                            onClick={() =>
                                                                router.post(
                                                                    `/admin/events/${event.id}/approve`,
                                                                    {},
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            <Check />
                                                            {t(
                                                                'review.approve',
                                                            )}
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            className="cursor-pointer"
                                                            onClick={() =>
                                                                router.post(
                                                                    `/admin/events/${event.id}/reject`,
                                                                    {},
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            <X />
                                                            {t('review.reject')}
                                                        </Button>
                                                    </>
                                                )}

                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    className="cursor-pointer text-destructive hover:text-destructive"
                                                    onClick={() => {
                                                        // Tickets go with it,
                                                        // so this asks first.
                                                        if (
                                                            confirm(
                                                                t(
                                                                    'review.confirm_delete',
                                                                    {
                                                                        n: event.tickets_count,
                                                                    },
                                                                ),
                                                            )
                                                        ) {
                                                            router.delete(
                                                                `/admin/events/${event.id}`,
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            );
                                                        }
                                                    }}
                                                >
                                                    <Trash2 />
                                                    {t('common.delete')}
                                                </Button>
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

AdminEvents.layout = {
    breadcrumbs: [{ title: 'review.title', href: eventsRoute() }],
};
