import { Form, Head } from '@inertiajs/react';
import { CalendarDays, Clock, MapPin, Users } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { LanguageToggle } from '@/components/language-toggle';
import { PlaceEdgeTab } from '@/components/place-edge-tab';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { localised, useLocale } from '@/lib/locale';
import type { PublicEvent, PublicPlace, SiblingEvent } from '@/types/public';

type Props = {
    event: PublicEvent;
    place: PublicPlace;
    siblings: SiblingEvent[];
};

export default function EventPage({ event, place, siblings }: Props) {
    const { locale } = useLocale();
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
            <Head title={title} />

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
                    {event.cover?.portrait && (
                        <picture>
                            <source
                                media="(min-width: 640px)"
                                srcSet={`/storage/${event.cover.landscape}`}
                            />
                            <img
                                src={`/storage/${event.cover.portrait}`}
                                alt=""
                                className="size-full object-cover"
                                fetchPriority="high"
                            />
                        </picture>
                    )}

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
                            {locale === 'ar' ? 'التاريخ' : 'Date'}
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
                            {locale === 'ar' ? 'الوقت' : 'Time'}
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
                            {locale === 'ar' ? 'المقاعد' : 'Seats'}
                        </p>
                        <p className="font-medium">
                            {soldOut
                                ? locale === 'ar'
                                    ? 'مكتمل'
                                    : 'Sold out'
                                : event.seats_remaining}
                        </p>
                    </div>
                </section>

                <p className="text-3xl font-bold text-primary">
                    {event.is_free
                        ? locale === 'ar'
                            ? 'مجاني'
                            : 'Free'
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
                            {locale === 'ar'
                                ? 'احجز تذكرتك'
                                : 'Reserve your ticket'}
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {locale === 'ar'
                                ? 'احجز الآن وادفع في المكان.'
                                : 'Reserve now, pay at the venue.'}
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
                                        {locale === 'ar'
                                            ? 'الاسم الكامل'
                                            : 'Full name'}
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
                                        {locale === 'ar'
                                            ? 'رقم الموبايل'
                                            : 'Mobile number'}
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
                                        {locale === 'ar'
                                            ? 'عدد الأشخاص'
                                            : 'Number of people'}
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
                                            {locale === 'ar'
                                                ? 'الشروط والملاحظات'
                                                : 'Rules & notes'}
                                        </legend>

                                        {event.rules.map((rule) => (
                                            <label
                                                key={rule.id}
                                                className="flex cursor-pointer items-start gap-3 text-sm"
                                            >
                                                <Checkbox
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
                                        ? locale === 'ar'
                                            ? 'اكتمل العدد'
                                            : 'Sold out'
                                        : locale === 'ar'
                                          ? 'احجز الآن'
                                          : 'Appoint now'}
                                </Button>

                                {!allRulesAccepted &&
                                    event.rules.length > 0 && (
                                        <p className="text-center text-xs text-muted-foreground">
                                            {locale === 'ar'
                                                ? 'يجب الموافقة على جميع الشروط أولًا.'
                                                : 'Accept all rules to continue.'}
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
