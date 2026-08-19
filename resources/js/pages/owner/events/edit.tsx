import { Head } from '@inertiajs/react';
import EventController from '@/actions/App/Http/Controllers/Owner/EventController';
import Heading from '@/components/heading';
import { MediaManager } from '@/components/media-manager';
import type { MediaItem } from '@/components/media-manager';
import { useTranslation } from '@/lib/translation';
import EventForm from './event-form';
import type { EventFormValues } from './event-form';

type Props = { event: EventFormValues & { id: number; media: MediaItem[] } };

export default function EditEvent({ event }: Props) {
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
                    action={EventController.update.form(event.id)}
                    values={event}
                    submitLabel={t('form.save')}
                />

                <MediaManager eventId={event.id} media={event.media} />
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
