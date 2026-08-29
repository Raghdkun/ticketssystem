import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Stepper } from '@/components/stepper';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { WaitingList } from '@/components/waiting-list';
import { localised, useLocale } from '@/lib/locale';
import { useTranslation } from '@/lib/translation';
import { cn } from '@/lib/utils';
import type { PublicEvent, PublicPlace } from '@/types/public';

type Props = {
    event: PublicEvent;
    place: PublicPlace;
    accepted: number[];
    onAcceptedChange: (ids: number[]) => void;
    quantity: number;
    onQuantityChange: (value: number) => void;
    /** Inside the phone sheet the panel supplies its own chrome. */
    bare?: boolean;
};

/**
 * The booking form.
 *
 * Shared so the phone sheet and the tablet/desktop panel are the same form
 * rather than two that drift apart. The parent owns the accepted-rules and
 * quantity state, because the sticky bar needs to know whether the form can
 * be submitted before it is on screen.
 */
export function BookingForm({
    event,
    place,
    accepted,
    onAcceptedChange,
    quantity,
    onQuantityChange,
    bare,
}: Props) {
    const { locale } = useLocale();
    const t = useTranslation();

    const soldOut = event.seats_remaining <= 0;
    const allRulesAccepted = event.rules.every((rule) =>
        accepted.includes(rule.id),
    );
    const canAppoint = event.is_open && !soldOut && allRulesAccepted;
    const total = event.price * quantity;

    return (
        <section
            id="appoint"
            className={
                bare
                    ? 'space-y-6'
                    : 'lg:shadow-brand space-y-6 rounded-xl border p-5'
            }
        >
            <div>
                {/* In the sheet the heading is the sheet's own. */}
                {!bare && (
                    <>
                        <h2 className="font-display text-lg font-semibold">
                            {soldOut
                                ? t('event.waitlist_title')
                                : t('event.reserve_title')}
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {soldOut
                                ? t('event.waitlist_hint')
                                : t('event.reserve_subtitle')}
                        </p>
                    </>
                )}

                <p
                    className={cn(
                        'font-display text-3xl font-semibold tracking-tight text-primary tabular-nums',
                        !bare && 'mt-4',
                    )}
                >
                    {event.is_free
                        ? t('event.free')
                        : `${event.price.toLocaleString('en-GB')} ${event.currency}`}
                    {!event.is_free && (
                        <span className="ms-2 align-middle text-sm font-medium text-muted-foreground">
                            {t('event.per_person')}
                        </span>
                    )}
                </p>
            </div>

            {/*
             * Sold out swaps the whole form for the waiting list rather than
             * disabling the button. A form you cannot submit is a dead end,
             * and seats genuinely do come back on this platform.
             */}
            {soldOut && event.is_open ? (
                <WaitingList event={event} place={place} />
            ) : (
                <Form
                    action={`/${place.slug}/${event.slug}/appoint`}
                    method="post"
                    className="space-y-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="full_name">
                                    {t('event.full_name')}
                                </Label>
                                <Input
                                    id="full_name"
                                    name="full_name"
                                    required
                                    autoComplete="name"
                                />
                                <InputError message={errors.full_name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="phone">
                                    {t('event.mobile')}
                                </Label>
                                <Input
                                    id="phone"
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

                            <div className="grid gap-3">
                                <Label htmlFor="quantity">
                                    {t('event.people')}
                                </Label>
                                <Stepper
                                    value={quantity}
                                    onChange={onQuantityChange}
                                    max={Math.min(
                                        event.max_per_appointment,
                                        Math.max(event.seats_remaining, 1),
                                    )}
                                    name="quantity"
                                    label={t('event.people')}
                                />
                                <InputError message={errors.quantity} />
                            </div>

                            {event.rules.length > 0 && (
                                <fieldset className="space-y-3 rounded-lg bg-muted/50 p-4">
                                    <legend className="text-sm font-medium">
                                        {t('event.rules')}
                                    </legend>

                                    {event.rules.map((rule) => (
                                        <label
                                            key={rule.id}
                                            htmlFor={`rule-${rule.id}`}
                                            // The whole row is the target:
                                            // a 16px checkbox is far below
                                            // a usable tap area, and this
                                            // gate is what unlocks booking.
                                            className="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg px-2 py-2 text-sm transition-colors hover:bg-background/60"
                                        >
                                            <Checkbox
                                                id={`rule-${rule.id}`}
                                                // A wrapping label does not
                                                // name a Radix checkbox the
                                                // way it names a native
                                                // input, so attach the rule
                                                // text explicitly.
                                                aria-label={localised(
                                                    locale,
                                                    rule.body_ar,
                                                    rule.body_en,
                                                )}
                                                className="cursor-pointer"
                                                checked={accepted.includes(
                                                    rule.id,
                                                )}
                                                onCheckedChange={(checked) =>
                                                    onAcceptedChange(
                                                        checked
                                                            ? [
                                                                  ...accepted,
                                                                  rule.id,
                                                              ]
                                                            : accepted.filter(
                                                                  (id) =>
                                                                      id !==
                                                                      rule.id,
                                                              ),
                                                    )
                                                }
                                            />
                                            <span>
                                                {localised(
                                                    locale,
                                                    rule.body_ar,
                                                    rule.body_en,
                                                )}
                                            </span>
                                            {accepted.includes(rule.id) && (
                                                <input
                                                    type="hidden"
                                                    name="accepted_rule_ids[]"
                                                    value={rule.id}
                                                />
                                            )}
                                        </label>
                                    ))}
                                </fieldset>
                            )}

                            {!event.is_free && quantity > 1 && (
                                <div className="flex items-baseline justify-between gap-3 rounded-xl bg-muted px-4 py-3">
                                    <span className="text-sm text-muted-foreground tabular-nums">
                                        {quantity} ×{' '}
                                        {event.price.toLocaleString('en-GB')}
                                    </span>

                                    <span className="font-display text-xl font-semibold text-primary tabular-nums">
                                        {total.toLocaleString('en-GB')}

                                        <span className="ms-1 text-sm font-medium">
                                            {event.currency}
                                        </span>
                                    </span>
                                </div>
                            )}

                            <Button
                                type="submit"
                                size="lg"
                                className="w-full"
                                disabled={processing || !canAppoint}
                            >
                                {soldOut
                                    ? t('event.sold_out')
                                    : t('event.appoint')}
                            </Button>

                            {!allRulesAccepted && event.rules.length > 0 && (
                                <p className="text-center text-xs text-muted-foreground">
                                    {t('event.accept_rules_first')}
                                </p>
                            )}
                        </>
                    )}
                </Form>
            )}
        </section>
    );
}
