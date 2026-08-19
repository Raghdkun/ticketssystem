import { Form, Head } from '@inertiajs/react';
import { CheckCircle2, UserX, Users, XCircle } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { localised, useLocale } from '@/lib/locale';
import { useTranslation } from '@/lib/translation';

type OwnerTicket = {
    arrived_quantity: number;
    token: string;
    full_name: string;
    phone: string;
    quantity: number;
    status: 'pending' | 'paid' | 'cancelled' | 'expired' | 'no_show';
    hold_expires_at: string | null;
    verified_at: string | null;
    created_at: string | null;
    event_title_en: string;
    event_title_ar: string;
};

export default function VerifyTicket({ ticket }: { ticket: OwnerTicket }) {
    const { locale } = useLocale();
    const t = useTranslation();
    const alreadyVerified = ticket.status === 'paid';
    // Default to the whole party; the door can dial it down when only some
    // of the group turns up.
    const [arrived, setArrived] = useState(ticket.quantity);
    const eventTitle = localised(
        locale,
        ticket.event_title_ar,
        ticket.event_title_en,
    );

    return (
        <>
            <Head title={`${t('owner.verify_title')} — ${ticket.full_name}`} />

            <div className="mx-auto w-full max-w-md space-y-6 p-4">
                <Heading
                    variant="small"
                    title={t('owner.verify_title')}
                    description={eventTitle}
                />

                <div className="flex flex-col items-center gap-2 rounded-xl border p-4 text-center text-sm">
                    <StatusBadge status={ticket.status} />
                    <span className="text-muted-foreground">
                        {alreadyVerified && ticket.verified_at
                            ? t('owner.already_verified', {
                                  time: new Date(
                                      ticket.verified_at,
                                  ).toLocaleString(
                                      locale === 'ar' ? 'ar-SY' : 'en-GB',
                                      {
                                          dateStyle: 'medium',
                                          timeStyle: 'short',
                                      },
                                  ),
                              })
                            : t('owner.arrived_of', {
                                  arrived: ticket.arrived_quantity,
                                  total: ticket.quantity,
                              })}
                    </span>
                </div>

                <dl className="grid grid-cols-2 gap-4 rounded-xl border p-4 text-sm">
                    <div className="col-span-2">
                        <dt className="text-muted-foreground">
                            {t('ticket.name')}
                        </dt>
                        <dd className="text-lg font-semibold">
                            {ticket.full_name}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">
                            {t('event.mobile')}
                        </dt>
                        <dd className="font-medium" dir="ltr">
                            {ticket.phone}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">
                            {t('ticket.people')}
                        </dt>
                        <dd className="inline-flex items-center gap-1.5 text-lg font-semibold">
                            <Users className="size-4" />
                            {ticket.quantity}
                        </dd>
                    </div>
                    <div className="col-span-2">
                        <dt className="text-muted-foreground">
                            {t('ticket.reference')}
                        </dt>
                        <dd className="font-mono text-xs" dir="ltr">
                            {ticket.token.toUpperCase()}
                        </dd>
                    </div>
                </dl>

                {/* State changes are POSTs, never a side effect of opening the URL. */}
                <div className="flex flex-col gap-3">
                    {ticket.quantity > 1 && (
                        <div className="grid gap-2 rounded-xl border p-4">
                            <Label htmlFor="arrived">
                                {t('owner.arrived')}
                            </Label>
                            <Input
                                id="arrived"
                                type="number"
                                inputMode="numeric"
                                min={1}
                                max={ticket.quantity}
                                value={arrived}
                                onChange={(e) =>
                                    setArrived(
                                        Math.max(
                                            1,
                                            Math.min(
                                                ticket.quantity,
                                                Number(e.target.value) || 1,
                                            ),
                                        ),
                                    )
                                }
                            />
                            <p className="text-xs text-muted-foreground">
                                {t('owner.arrived_hint', {
                                    n: ticket.quantity,
                                })}
                            </p>
                        </div>
                    )}

                    <Form action={`/verify/${ticket.token}/paid`} method="post">
                        {({ processing }) => (
                            <>
                                <input
                                    type="hidden"
                                    name="arrived"
                                    value={arrived}
                                />
                                <Button
                                    type="submit"
                                    size="lg"
                                    className="w-full cursor-pointer"
                                    disabled={processing}
                                >
                                    <CheckCircle2 />
                                    {arrived === ticket.quantity
                                        ? t('owner.check_in_all', {
                                              n: ticket.quantity,
                                          })
                                        : t('owner.check_in_some', {
                                              n: arrived,
                                          })}
                                </Button>
                            </>
                        )}
                    </Form>

                    <Form
                        action={`/verify/${ticket.token}/no-show`}
                        method="post"
                    >
                        {({ processing }) => (
                            <Button
                                type="submit"
                                variant="outline"
                                className="w-full cursor-pointer"
                                disabled={
                                    processing || ticket.status === 'no_show'
                                }
                            >
                                <UserX />
                                {t('owner.no_show')}
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
                                {t('owner.cancel_ticket')}
                            </Button>
                        )}
                    </Form>
                </div>
            </div>
        </>
    );
}
