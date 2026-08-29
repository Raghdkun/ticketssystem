import { Form } from '@inertiajs/react';
import { CopyPlus } from 'lucide-react';
import { useState } from 'react';
import EventController from '@/actions/App/Http/Controllers/Owner/EventController';
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/lib/translation';

const CADENCES = ['weekly', 'fortnightly', 'monthly', 'daily'] as const;

/**
 * Copy an event forward on a cadence.
 *
 * A weekly night is the same event with a different date, and retyping the
 * rules, perks, price and seat count every week is the most repetitive thing
 * an owner does here. Copies land as drafts, always: the dates were computed
 * rather than chosen, and somebody should look at them before they go public.
 */
export function RepeatEvent({ eventId }: { eventId: number }) {
    const t = useTranslation();
    const [open, setOpen] = useState(false);
    const [cadence, setCadence] = useState('weekly');
    const [count, setCount] = useState('4');

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <button
                    type="button"
                    className="inline-flex cursor-pointer items-center gap-1.5 text-xs text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                >
                    <CopyPlus className="size-3.5" />
                    {t('owner.repeat')}
                </button>
            </DialogTrigger>

            <DialogContent>
                <DialogTitle>{t('owner.repeat_title')}</DialogTitle>
                <DialogDescription>{t('owner.repeat_hint')}</DialogDescription>

                <Form
                    action={EventController.repeat(eventId)}
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="cadence">
                                    {t('owner.repeat_cadence')}
                                </Label>
                                <Select
                                    value={cadence}
                                    onValueChange={setCadence}
                                >
                                    <SelectTrigger id="cadence">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {CADENCES.map((key) => (
                                            <SelectItem key={key} value={key}>
                                                {t(`owner.cadence.${key}`)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {/* Radix selects are not form controls, so the
                                    value is submitted by a hidden input. */}
                                <input
                                    type="hidden"
                                    name="cadence"
                                    value={cadence}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="count">
                                    {t('owner.repeat_count')}
                                </Label>
                                <Select value={count} onValueChange={setCount}>
                                    <SelectTrigger id="count">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {Array.from(
                                            { length: 12 },
                                            (_, i) => `${i + 1}`,
                                        ).map((n) => (
                                            <SelectItem key={n} value={n}>
                                                {n}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <input
                                    type="hidden"
                                    name="count"
                                    value={count}
                                />
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="outline" type="button">
                                        {t('common.cancel')}
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {t('owner.repeat_create')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
