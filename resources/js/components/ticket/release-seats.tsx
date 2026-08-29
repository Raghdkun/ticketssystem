import { Form } from '@inertiajs/react';
import { MoreHorizontal, TicketX } from 'lucide-react';
import { useState } from 'react';
import TicketController from '@/actions/App/Http/Controllers/Public/TicketController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslation } from '@/lib/translation';

/**
 * Letting the holder give their seats back.
 *
 * Deliberately not a button on the ticket. Someone opening this page is on
 * their way to the venue, and the loudest control on a ticket should never be
 * the one that destroys it -- so it lives behind an overflow menu and asks
 * once before acting.
 *
 * The seats are worth reclaiming, though: a hold that lapses silently is a
 * seat the venue could have sold and a person who never told anyone.
 */
export function ReleaseSeats({ token }: { token: string }) {
    const t = useTranslation();
    const [confirming, setConfirming] = useState(false);

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={t('ticket.more_options')}
                    >
                        <MoreHorizontal />
                    </Button>
                </DropdownMenuTrigger>

                <DropdownMenuContent align="end">
                    <DropdownMenuItem
                        variant="destructive"
                        onSelect={() => setConfirming(true)}
                    >
                        <TicketX />
                        {t('ticket.release')}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <Dialog open={confirming} onOpenChange={setConfirming}>
                <DialogContent>
                    <DialogTitle>{t('ticket.release_confirm')}</DialogTitle>
                    <DialogDescription>
                        {t('ticket.release_hint')}
                    </DialogDescription>

                    <Form
                        {...TicketController.release.form(token)}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setConfirming(false)}
                    >
                        {({ processing }) => (
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="outline" type="button">
                                        {t('common.cancel')}
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    {t('ticket.release')}
                                </Button>
                            </DialogFooter>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
        </>
    );
}
