import { Form } from '@inertiajs/react';
import { Archive, Trash2 } from 'lucide-react';
import { useState } from 'react';
import EventController from '@/actions/App/Http/Controllers/Owner/EventController';
import { HoldSubmit } from '@/components/motion/hold-submit';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useTranslation } from '@/lib/translation';

/**
 * The way out of an event, at the very bottom of its form.
 *
 * Two different things, one control: an event nobody holds a ticket for is
 * deleted outright, files and all; one that people have booked is archived
 * instead, because a ticket is a record on somebody's phone. The wording
 * says which will happen before the owner confirms, so there is no surprise
 * either way.
 */
export function DeleteEvent({
    eventId,
    title,
    holders,
    archived,
}: {
    eventId: number;
    title: string;
    /** Paid or still-held bookings: what deleting would take with it. */
    holders: number;
    /** Already archived: the control only offers a real delete, if any. */
    archived: boolean;
}) {
    const t = useTranslation();
    const [open, setOpen] = useState(false);
    const willArchive = holders > 0;

    // An archived event with holders has nowhere further to go.
    if (archived && willArchive) {
        return null;
    }

    const Icon = willArchive ? Archive : Trash2;

    return (
        <section className="rounded-xl border border-destructive/30 p-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="min-w-0">
                    <h2 className="text-sm font-semibold">
                        {willArchive
                            ? t('owner.archive_event')
                            : t('owner.delete_event')}
                    </h2>
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        {willArchive
                            ? t('owner.archive_event_hint', { n: holders })
                            : t('owner.delete_event_hint')}
                    </p>
                </div>

                <Dialog open={open} onOpenChange={setOpen}>
                    <DialogTrigger asChild>
                        <Button
                            type="button"
                            variant={willArchive ? 'outline' : 'destructive'}
                        >
                            <Icon />
                            {willArchive
                                ? t('owner.archive_event')
                                : t('owner.delete_event')}
                        </Button>
                    </DialogTrigger>

                    <DialogContent>
                        <DialogTitle>
                            {willArchive
                                ? t('owner.archive_confirm_title', { title })
                                : t('owner.delete_confirm_title', { title })}
                        </DialogTitle>
                        <DialogDescription>
                            {willArchive
                                ? t('owner.archive_event_hint', { n: holders })
                                : t('owner.delete_event_hint')}
                        </DialogDescription>

                        <Form
                            {...EventController.destroy.form(eventId)}
                            onSuccess={() => setOpen(false)}
                        >
                            {({ processing }) => (
                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button variant="outline" type="button">
                                            {t('common.cancel')}
                                        </Button>
                                    </DialogClose>
                                    <HoldSubmit
                                        disabled={processing}
                                        tone={
                                            willArchive
                                                ? 'primary'
                                                : 'destructive'
                                        }
                                        doneLabel={t('common.done')}
                                    >
                                        {t('common.hold_to_confirm')}
                                    </HoldSubmit>
                                </DialogFooter>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            </div>
        </section>
    );
}
