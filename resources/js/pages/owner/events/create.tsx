import { Head } from '@inertiajs/react';
import EventController from '@/actions/App/Http/Controllers/Owner/EventController';
import Heading from '@/components/heading';
import EventForm from './event-form';

export default function CreateEvent() {
    return (
        <>
            <Head title="New event" />

            <div className="max-w-4xl space-y-6 p-4">
                <Heading
                    variant="small"
                    title="New event"
                    description="Publish an event and start taking appointments."
                />

                <EventForm
                    action={EventController.store.form()}
                    submitLabel="Create event"
                />
            </div>
        </>
    );
}

CreateEvent.layout = {
    breadcrumbs: [
        { title: 'Events', href: EventController.index() },
        { title: 'New', href: EventController.create() },
    ],
};
