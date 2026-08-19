import { Head } from '@inertiajs/react';
import EventController from '@/actions/App/Http/Controllers/Owner/EventController';
import Heading from '@/components/heading';
import { useTranslation } from '@/lib/translation';
import EventForm from './event-form';

export default function CreateEvent() {
    const t = useTranslation();

    return (
        <>
            <Head title={t('owner.new_event')} />

            <div className="max-w-4xl space-y-6 p-4">
                <Heading
                    variant="small"
                    title={t('owner.new_event')}
                    description={t('form.create_sub')}
                />

                <EventForm
                    action={EventController.store.form()}
                    submitLabel={t('form.create')}
                />
            </div>
        </>
    );
}

CreateEvent.layout = {
    breadcrumbs: [
        { title: 'owner.events', href: EventController.index() },
        { title: 'owner.new_event', href: EventController.create() },
    ],
};
