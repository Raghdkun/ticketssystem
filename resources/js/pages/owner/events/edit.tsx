import { Head } from '@inertiajs/react';
import EventController from '@/actions/App/Http/Controllers/Owner/EventController';
import Heading from '@/components/heading';
import EventForm from './event-form';
import type { EventFormValues } from './event-form';

type Props = { event: EventFormValues & { id: number } };

export default function EditEvent({ event }: Props) {
    return (
        <>
            <Head title={`Edit ${event.title_en}`} />

            <div className="max-w-4xl space-y-6 p-4">
                <Heading
                    variant="small"
                    title="Edit event"
                    description={event.title_en}
                />

                <EventForm
                    action={EventController.update.form(event.id)}
                    values={event}
                    submitLabel="Save changes"
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
