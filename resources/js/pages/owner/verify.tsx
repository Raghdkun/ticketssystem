import { Form, Head } from '@inertiajs/react';
import { CheckCircle2, Users, XCircle } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type OwnerTicket = {
    token: string;
    full_name: string;
    phone: string;
    quantity: number;
    status: 'pending' | 'paid' | 'cancelled' | 'expired';
    hold_expires_at: string | null;
    verified_at: string | null;
    created_at: string | null;
    event_title_en: string;
    event_title_ar: string;
};

const statusStyles: Record<OwnerTicket['status'], string> = {
    pending:
        'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200',
    paid: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
    cancelled: 'bg-red-100 text-red-900 dark:bg-red-950 dark:text-red-200',
    expired:
        'bg-neutral-200 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300',
};

export default function VerifyTicket({ ticket }: { ticket: OwnerTicket }) {
    const alreadyVerified = ticket.status === 'paid';

    return (
        <>
            <Head title={`Verify ${ticket.full_name}`} />

            <div className="mx-auto w-full max-w-md space-y-6 p-4">
                <Heading
                    variant="small"
                    title="Verify ticket"
                    description={ticket.event_title_en}
                />

                <div
                    className={cn(
                        'rounded-xl py-3 text-center text-sm font-semibold',
                        statusStyles[ticket.status],
                    )}
                >
                    {alreadyVerified && ticket.verified_at
                        ? `Already verified at ${new Date(ticket.verified_at).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' })}`
                        : ticket.status}
                </div>

                <dl className="grid grid-cols-2 gap-4 rounded-xl border p-4 text-sm">
                    <div className="col-span-2">
                        <dt className="text-muted-foreground">Name</dt>
                        <dd className="text-lg font-semibold">
                            {ticket.full_name}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Mobile</dt>
                        <dd className="font-medium" dir="ltr">
                            {ticket.phone}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">People</dt>
                        <dd className="inline-flex items-center gap-1.5 text-lg font-semibold">
                            <Users className="size-4" />
                            {ticket.quantity}
                        </dd>
                    </div>
                    <div className="col-span-2">
                        <dt className="text-muted-foreground">Reference</dt>
                        <dd className="font-mono text-xs" dir="ltr">
                            {ticket.token.toUpperCase()}
                        </dd>
                    </div>
                </dl>

                {/* State changes are POSTs, never a side effect of opening the URL. */}
                <div className="flex flex-col gap-3">
                    <Form action={`/verify/${ticket.token}/paid`} method="post">
                        {({ processing }) => (
                            <Button
                                type="submit"
                                size="lg"
                                className="w-full"
                                disabled={processing || alreadyVerified}
                            >
                                <CheckCircle2 />
                                {alreadyVerified
                                    ? 'Already paid'
                                    : 'Mark as paid'}
                            </Button>
                        )}
                    </Form>

                    <Form
                        action={`/verify/${ticket.token}/cancel`}
                        method="post"
                    >
                        {({ processing }) => (
                            <Button
                                type="submit"
                                variant="outline"
                                className="w-full"
                                disabled={
                                    processing || ticket.status === 'cancelled'
                                }
                            >
                                <XCircle />
                                Cancel ticket
                            </Button>
                        )}
                    </Form>
                </div>
            </div>
        </>
    );
}
