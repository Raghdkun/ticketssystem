import { Head, Link } from '@inertiajs/react';
import { CalendarDays, Plus, Ticket as TicketIcon } from 'lucide-react';
import EventController from '@/actions/App/Http/Controllers/Owner/EventController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type EventRow = {
    id: number;
    slug: string;
    title_ar: string;
    title_en: string;
    status: string;
    starts_at: string;
    total_quantity: number;
    seats_taken: number;
    tickets_count: number;
    cover: string | null;
    primary_color: string | null;
};

type Props = {
    place: { name_ar: string; name_en: string; slug: string };
    events: EventRow[];
};

const statusVariant: Record<string, 'default' | 'secondary' | 'outline'> = {
    published: 'default',
    draft: 'secondary',
    archived: 'outline',
};

export default function EventsIndex({ place, events }: Props) {
    return (
        <>
            <Head title="Events" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Events"
                        description={place.name_en}
                    />

                    <Button asChild>
                        <Link href={EventController.create()}>
                            <Plus />
                            New event
                        </Link>
                    </Button>
                </div>

                {events.length === 0 ? (
                    <div className="rounded-xl border border-dashed p-12 text-center">
                        <p className="text-sm text-muted-foreground">
                            No events yet. Create your first one to start taking
                            appointments.
                        </p>
                    </div>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {events.map((event) => (
                            <Link
                                key={event.id}
                                href={EventController.edit(event.id)}
                                className="group overflow-hidden rounded-xl border transition hover:shadow-md"
                            >
                                <div
                                    className="relative aspect-video bg-muted"
                                    style={{
                                        backgroundColor:
                                            event.primary_color ?? undefined,
                                    }}
                                >
                                    {event.cover && (
                                        <img
                                            src={`/storage/${event.cover}`}
                                            alt=""
                                            className="size-full object-cover"
                                            loading="lazy"
                                        />
                                    )}
                                </div>

                                <div className="space-y-3 p-4">
                                    <div className="flex items-start justify-between gap-2">
                                        <h2 className="leading-tight font-medium">
                                            {event.title_en}
                                        </h2>
                                        <Badge
                                            variant={
                                                statusVariant[event.status] ??
                                                'secondary'
                                            }
                                        >
                                            {event.status}
                                        </Badge>
                                    </div>

                                    <p
                                        className="text-sm text-muted-foreground"
                                        dir="rtl"
                                    >
                                        {event.title_ar}
                                    </p>

                                    <div className="flex flex-wrap gap-4 text-xs text-muted-foreground">
                                        <span className="inline-flex items-center gap-1.5">
                                            <CalendarDays className="size-3.5" />
                                            {new Date(
                                                event.starts_at,
                                            ).toLocaleDateString()}
                                        </span>
                                        <span className="inline-flex items-center gap-1.5">
                                            <TicketIcon className="size-3.5" />
                                            {event.seats_taken} /{' '}
                                            {event.total_quantity} seats
                                        </span>
                                    </div>
                                </div>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

EventsIndex.layout = {
    breadcrumbs: [{ title: 'Events', href: EventController.index() }],
};
