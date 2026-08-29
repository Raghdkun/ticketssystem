import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/lib/translation';
import type { PublicEvent, PublicPlace } from '@/types/public';

/**
 * The queue for a sold-out event.
 *
 * Sold out is not an ending here: holds lapse and people cancel, so seats come
 * back regularly. Without somewhere to leave a name the visitor hits a dead
 * end and the venue never learns there was demand it could not meet.
 *
 * The number is the point. There is no mailer, so a push reaches whoever opted
 * in and the venue can call everybody else off the list.
 */
export function WaitingList({
    event,
    place,
}: {
    event: PublicEvent;
    place: PublicPlace;
}) {
    const t = useTranslation();

    return (
        <Form
            action={`/${place.slug}/${event.slug}/watch`}
            method="post"
            resetOnSuccess
            className="space-y-5"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="watch_full_name">
                            {t('event.full_name')}
                        </Label>
                        <Input
                            id="watch_full_name"
                            name="full_name"
                            required
                            autoComplete="name"
                        />
                        <InputError message={errors.full_name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="watch_phone">{t('event.mobile')}</Label>
                        <Input
                            id="watch_phone"
                            name="phone"
                            type="tel"
                            inputMode="tel"
                            dir="ltr"
                            placeholder="09XXXXXXXX"
                            required
                            autoComplete="tel"
                        />
                        <InputError message={errors.phone} />
                    </div>

                    <Button
                        type="submit"
                        size="lg"
                        className="w-full"
                        disabled={processing}
                    >
                        {t('event.notify_me')}
                    </Button>

                    <p className="text-center text-xs text-muted-foreground">
                        {t('event.waitlist_note')}
                    </p>
                </>
            )}
        </Form>
    );
}
