import { Form, Head } from '@inertiajs/react';
import { CalendarDays, Clock, MapPin, Users } from 'lucide-react';
import { useState } from 'react';
import { EventCover } from '@/components/event-cover';
import { FlashToaster } from '@/components/flash-toaster';
import InputError from '@/components/input-error';
import { LanguageToggle } from '@/components/language-toggle';
import { PlaceEdgeTab } from '@/components/place-edge-tab';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { localised, useLocale } from '@/lib/locale';
import { useTranslation } from '@/lib/translation';
import type { PublicEvent, PublicPlace, SiblingEvent } from '@/types/public';

type Props = {
    event: PublicEvent;
    place: PublicPlace;
    siblings: SiblingEvent[];
};

export default function EventPage({ event, place, siblings }: Props) {
    const { locale } = useLocale();
    const t = useTranslation();
    const [accepted, setAccepted] = useState<number[]>([]);

    const title = localised(locale, event.title_ar, event.title_en);
    const description = localised(
        locale,
        event.description_ar,
        event.description_en,
    );
    const placeName = localised(locale, place.name_ar, place.name_en);

    const allRulesAccepted = event.rules.every((rule) =>
        accepted.includes(rule.id),
    );
    const soldOut = event.seats_remaining <= 0;
    const canAppoint = event.is_open && !soldOut && allRulesAccepted;

    return (
        <div
            className="event-theme min-h-dvh bg-background"
            style={
                {
                    '--event-primary': event.theme.primary,
                    '--event-secondary': event.theme.secondary,
                    '--event-on-primary': event.theme.on_primary,
                } as React.CSSProperties
            }
        >
            <Head title={title}>
                <meta
                    name="description"
                    content={`${title} — ${placeName}. ${
                        description ? description.slice(0, 140) : ''
                    }`.trim()}
                />
            </Head>

            <FlashToaster />
            <PlaceEdgeTab place={place} siblings={siblings} />

            <header className="relative">
                <div className="absolute end-4 top-4 z-30">
                    <LanguageToggle />
                </div>

                <div
                    className="relative aspect-[3/4] w-full overflow-hidden sm:aspect-[16/9]"
                    style={{
                        background: `linear-gradient(135deg, var(--event-primary), var(--event-secondary))`,
                    }}
                >
                    <EventCover cover={event.cover} alt="" priority />

                    <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent" />

                    <div className="absolute inset-x-0 bottom-0 p-5 sm:p-8">
                        <p className="inline-flex items-center gap-1.5 text-sm font-medium text-white/80">
                            <MapPin className="size-4" />
                            {placeName}
                        </p>
                        <h1 className="mt-1 text-3xl leading-tight font-bold text-white sm:text-4xl">
                            {title}
                        </h1>
                    </div>
                </div>
            </header>

            <main className="mx-auto w-full max-w-2xl space-y-8 p-5 sm:p-8">
                <section className="grid grid-cols-2 gap-4 rounded-xl border p-4 text-sm sm:grid-cols-3">
                    <div className="space-y-1">
                        <p className="inline-flex items-center gap-1.5 text-muted-foreground">
                            <CalendarDays className="size-4" />
                            {t('event.date')}
                        </p>
                        <p className="font-medium">
                            {new Date(event.starts_at).toLocaleDateString(
                                locale === 'ar' ? 'ar-SY' : 'en-GB',
                                { dateStyle: 'medium' },
                            )}
                        </p>
                    </div>

                    <div className="space-y-1">
                        <p className="inline-flex items-center gap-1.5 text-muted-foreground">
                            <Clock className="size-4" />
                            {t('event.time')}
                        </p>
                        <p className="font-medium">
                            {new Date(event.starts_at).toLocaleTimeString(
                                locale === 'ar' ? 'ar-SY' : 'en-GB',
                                { timeStyle: 'short' },
                            )}
                        </p>
                    </div>

                    <div className="space-y-1">
                        <p className="inline-flex items-center gap-1.5 text-muted-foreground">
                            <Users className="size-4" />
                            {t('event.seats')}
                        </p>
                        <p className="font-medium">
                            {soldOut
                                ? t('event.sold_out')
                                : event.seats_remaining}
                        </p>
                    </div>
                </section>

                <p className="inline-block rounded-xl bg-primary px-4 py-2 text-3xl font-bold text-primary-foreground">
                    {event.is_free
                        ? t('event.free')
                        : `${event.price.toLocaleString()} ${event.currency}`}
                </p>

                {description && (
                    <p className="leading-relaxed whitespace-pre-line text-muted-foreground">
                        {description}
                    </p>
                )}

                <section
                    id="appoint"
                    className="space-y-6 rounded-xl border p-5"
                >
                    <div>
                        <h2 className="text-lg font-semibold">
                            {t('event.reserve_title')}
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t('event.reserve_subtitle')}
                        </p>
                    </div>

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

                                <div className="grid gap-2">
                                    <Label htmlFor="quantity">
                                        {t('event.people')}
                                    </Label>
                                    <Input
                                        id="quantity"
                                        name="quantity"
                                        type="number"
                                        min={1}
                                        max={Math.min(
                                            event.max_per_appointment,
                                            Math.max(event.seats_remaining, 1),
                                        )}
                                        defaultValue={1}
                                        required
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
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        setAccepted((prev) =>
                                                            checked
                                                                ? [
                                                                      ...prev,
                                                                      rule.id,
                                                                  ]
                                                                : prev.filter(
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

                                {!allRulesAccepted &&
                                    event.rules.length > 0 && (
                                        <p className="text-center text-xs text-muted-foreground">
                                            {t('event.accept_rules_first')}
                                        </p>
                                    )}
                            </>
                        )}
                    </Form>
                </section>
            </main>
        </div>
    );
}
