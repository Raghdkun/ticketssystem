import { router, usePage } from '@inertiajs/react';
import { Archive, CheckCircle2, Clock, FileEdit } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { localised, useLocale } from '@/lib/locale';
import { useTranslation } from '@/lib/translation';

export type SavedEvent = {
    status: 'draft' | 'published' | 'pending_review' | 'archived';
    title_ar: string;
    title_en: string;
    url: string | null;
    unlisted: boolean;
    copies: number;
};

const ICONS = {
    draft: FileEdit,
    published: CheckCircle2,
    pending_review: Clock,
    archived: Archive,
} as const;

/**
 * What just happened to the event the owner saved.
 *
 * A toast said "created" whether the event went live or was parked for
 * review, and owners told people to book events nobody could see. This
 * names the state in a sentence, and for a live event hands over the link
 * people will actually use -- the one thing worth doing next.
 *
 * The flash lives exactly one request, so the dialog is open while the
 * payload is there and has not been dismissed. Dismissal is remembered by
 * identity rather than cleared with an effect: the same payload object
 * survives a partial reload, and a dialog that reopened on every filter
 * click would be worse than the toast it replaced.
 */
export function SavedEventDialog() {
    const t = useTranslation();
    const { locale } = useLocale();
    const flash = usePage<{ flash?: { saved_event?: SavedEvent } }>().props
        .flash;
    const [dismissed, setDismissed] = useState<SavedEvent | null>(null);
    const saved =
        flash?.saved_event && flash.saved_event !== dismissed
            ? flash.saved_event
            : null;

    if (saved === null) {
        return null;
    }

    const Icon = ICONS[saved.status];
    const body =
        saved.status === 'published' && saved.unlisted
            ? t('owner.saved.published_unlisted_body')
            : t(`owner.saved.${saved.status}_body`);

    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open) {
                    setDismissed(saved);
                }
            }}
        >
            <DialogContent>
                <div className="flex items-start gap-3">
                    <span className="grid size-10 shrink-0 place-items-center rounded-full bg-primary text-primary-foreground">
                        <Icon className="size-5" aria-hidden="true" />
                    </span>
                    <div className="min-w-0 space-y-1">
                        <DialogTitle>
                            {t(`owner.saved.${saved.status}`)}
                        </DialogTitle>
                        <p className="truncate text-sm font-semibold">
                            {localised(locale, saved.title_ar, saved.title_en)}
                        </p>
                        <DialogDescription>{body}</DialogDescription>
                        {saved.copies > 0 && (
                            <p className="text-sm text-muted-foreground">
                                {t('owner.saved.copies', {
                                    count: saved.copies,
                                })}
                            </p>
                        )}
                    </div>
                </div>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="outline" type="button">
                            {t('common.close')}
                        </Button>
                    </DialogClose>
                    {saved.url && (
                        <Button
                            type="button"
                            onClick={() => router.visit(saved.url as string)}
                        >
                            {t('owner.saved.view')}
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
