import { Head } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';
import EventController from '@/actions/App/Http/Controllers/Owner/EventController';
import { EventQrCard } from '@/components/event-qr-card';
import Heading from '@/components/heading';
import { MediaManager } from '@/components/media-manager';
import type { MediaItem } from '@/components/media-manager';
import { DeleteEvent } from '@/components/owner/delete-event';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/lib/translation';
import EventForm from './event-form';
import type {
    CommercialSummary,
    EventFormValues,
    LocationOption,
} from './event-form';

type Props = {
    event: EventFormValues & { id: number; media: MediaItem[] };
    locations: LocationOption[];
    /** Paid or still-held bookings: what deleting would take with it. */
    holders: number;
    commercial: CommercialSummary | null;
};

export default function EditEvent({
    event,
    locations,
    holders,
    commercial,
}: Props) {
    const t = useTranslation();

    return (
        <>
            <Head title={`${t('owner.edit_event')} — ${event.title_en}`} />

            <div className="max-w-4xl space-y-6 p-4">
                <Heading
                    variant="small"
                    title={t('owner.edit_event')}
                    description={event.title_en}
                />

                <EventForm
                    locations={locations}
                    commercial={commercial}
                    action={EventController.update.form(event.id)}
                    values={event}
                    submitLabel={t('form.save')}
                />

                <EventQrCard eventId={event.id} />

                <Button asChild variant="outline" className="w-full">
                    <a href={`/owner/events/${event.id}/poster`}>
                        <Sparkles />
                        {t('poster.title')}
                    </a>
                </Button>

                <MediaManager eventId={event.id} media={event.media} />

                <DeleteEvent
                    eventId={event.id}
                    title={event.title_en}
                    holders={holders}
                    archived={event.status === 'archived'}
                />
            </div>
        </>
    );
}

EditEvent.layout = {
    breadcrumbs: [
        { title: 'owner.events', href: EventController.index() },
        { title: 'owner.edit_event', href: '#' },
    ],
};
