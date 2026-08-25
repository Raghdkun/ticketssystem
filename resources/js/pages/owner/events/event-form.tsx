import { Form } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/lib/translation';

export type EventRule = { body_ar: string; body_en: string };
export type EventPerk = EventRule;

export type EventFormValues = {
    title_ar: string;
    title_en: string;
    description_ar: string | null;
    description_en: string | null;
    price: number;
    currency: string;
    total_quantity: number;
    max_per_appointment: number;
    hold_hours: number;
    starts_at: string;
    ends_at: string | null;
    appointments_close_at: string;
    status: string;
    cover?: string | null;
    rules: EventRule[];
    perks: EventPerk[];
};

type Props = {
    /** Wayfinder form props, e.g. EventController.store.form() */
    action: Record<string, unknown>;
    values?: Partial<EventFormValues>;
    submitLabel: string;
};

/**
 * Field wrapper: label, control, and its validation message.
 */
function Field({
    id,
    label,
    error,
    children,
    hint,
}: {
    id: string;
    label: string;
    error?: string;
    children: React.ReactNode;
    hint?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            {children}
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
            <InputError message={error} />
        </div>
    );
}

export default function EventForm({ action, values, submitLabel }: Props) {
    const t = useTranslation();
    const [rules, setRules] = useState<EventRule[]>(values?.rules ?? []);
    const [perks, setPerks] = useState<EventPerk[]>(values?.perks ?? []);

    return (
        <Form
            {...action}
            options={{ preserveScroll: true }}
            className="space-y-8"
            encType="multipart/form-data"
        >
            {({ processing, errors }) => (
                <>
                    <section className="grid gap-4 sm:grid-cols-2">
                        <Field
                            id="title_en"
                            label={t('form.title_en')}
                            error={errors.title_en}
                        >
                            <Input
                                id="title_en"
                                name="title_en"
                                dir="ltr"
                                required
                                defaultValue={values?.title_en}
                            />
                        </Field>

                        <Field
                            id="title_ar"
                            label={t('form.title_ar')}
                            error={errors.title_ar}
                        >
                            <Input
                                id="title_ar"
                                name="title_ar"
                                required
                                dir="rtl"
                                defaultValue={values?.title_ar}
                            />
                        </Field>

                        <Field
                            id="description_en"
                            label={t('form.desc_en')}
                            error={errors.description_en}
                        >
                            <Textarea
                                id="description_en"
                                name="description_en"
                                dir="ltr"
                                defaultValue={values?.description_en ?? ''}
                            />
                        </Field>

                        <Field
                            id="description_ar"
                            label={t('form.desc_ar')}
                            error={errors.description_ar}
                        >
                            <Textarea
                                id="description_ar"
                                name="description_ar"
                                dir="rtl"
                                defaultValue={values?.description_ar ?? ''}
                            />
                        </Field>
                    </section>

                    <section className="grid gap-4 sm:grid-cols-3">
                        <Field
                            id="price"
                            label={t('form.price')}
                            error={errors.price}
                            hint={t('form.price_hint')}
                        >
                            <Input
                                id="price"
                                name="price"
                                type="number"
                                min={0}
                                step="0.01"
                                required
                                defaultValue={values?.price ?? 0}
                            />
                        </Field>

                        <Field
                            id="currency"
                            label={t('form.currency')}
                            error={errors.currency}
                        >
                            <Input
                                id="currency"
                                name="currency"
                                maxLength={3}
                                required
                                defaultValue={values?.currency ?? 'SYP'}
                            />
                        </Field>

                        <Field
                            id="total_quantity"
                            label={t('form.total_seats')}
                            error={errors.total_quantity}
                        >
                            <Input
                                id="total_quantity"
                                name="total_quantity"
                                type="number"
                                min={1}
                                required
                                defaultValue={values?.total_quantity ?? 100}
                            />
                        </Field>

                        <Field
                            id="max_per_appointment"
                            label={t('form.max_per')}
                            error={errors.max_per_appointment}
                        >
                            <Input
                                id="max_per_appointment"
                                name="max_per_appointment"
                                type="number"
                                min={1}
                                max={50}
                                required
                                defaultValue={values?.max_per_appointment ?? 10}
                            />
                        </Field>

                        <Field
                            id="hold_hours"
                            label={t('form.hold_hours')}
                            error={errors.hold_hours}
                            hint={t('form.hold_hint')}
                        >
                            <Input
                                id="hold_hours"
                                name="hold_hours"
                                type="number"
                                min={1}
                                max={720}
                                required
                                defaultValue={values?.hold_hours ?? 24}
                            />
                        </Field>

                        <Field
                            id="status"
                            label={t('form.status')}
                            error={errors.status}
                        >
                            <select
                                id="status"
                                name="status"
                                defaultValue={values?.status ?? 'draft'}
                                className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                            >
                                <option value="draft">
                                    {t('event.status.draft')}
                                </option>
                                <option value="published">
                                    {t('event.status.published')}
                                </option>
                                <option value="archived">
                                    {t('event.status.archived')}
                                </option>
                            </select>
                        </Field>
                    </section>

                    <section className="grid gap-4 sm:grid-cols-3">
                        <Field
                            id="starts_at"
                            label={t('form.starts_at')}
                            error={errors.starts_at}
                        >
                            <Input
                                id="starts_at"
                                name="starts_at"
                                type="datetime-local"
                                required
                                defaultValue={values?.starts_at}
                            />
                        </Field>

                        <Field
                            id="ends_at"
                            label={t('form.ends_at')}
                            error={errors.ends_at}
                        >
                            <Input
                                id="ends_at"
                                name="ends_at"
                                type="datetime-local"
                                defaultValue={values?.ends_at ?? ''}
                            />
                        </Field>

                        <Field
                            id="appointments_close_at"
                            label={t('form.closes_at')}
                            error={errors.appointments_close_at}
                            hint={t('form.closes_hint')}
                        >
                            <Input
                                id="appointments_close_at"
                                name="appointments_close_at"
                                type="datetime-local"
                                required
                                defaultValue={values?.appointments_close_at}
                            />
                        </Field>
                    </section>

                    <section className="space-y-4">
                        <Field
                            id="cover"
                            label={t('form.cover')}
                            error={errors.cover}
                            hint={t('form.cover_hint')}
                        >
                            <Input
                                id="cover"
                                name="cover"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                            />
                        </Field>

                        {values?.cover && (
                            <img
                                src={`/storage/${values.cover}`}
                                alt={t('form.cover')}
                                className="aspect-video w-full max-w-sm rounded-lg object-cover"
                            />
                        )}
                    </section>

                    <section className="space-y-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <h3 className="text-sm font-medium">
                                    {t('form.rules_title')}
                                </h3>
                                <p className="text-xs text-muted-foreground">
                                    Attendees must accept every rule before they
                                    can appoint.
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    setRules([
                                        ...rules,
                                        { body_ar: '', body_en: '' },
                                    ])
                                }
                            >
                                <Plus />
                                {t('form.add_rule')}
                            </Button>
                        </div>

                        {rules.map((rule, index) => (
                            <div key={index} className="flex items-end gap-2">
                                <div className="grid flex-1 gap-2 sm:grid-cols-2">
                                    <Input
                                        name={`rules[${index}][body_en]`}
                                        dir="ltr"
                                        placeholder={t('form.rule_en')}
                                        defaultValue={rule.body_en}
                                        required
                                    />
                                    <Input
                                        name={`rules[${index}][body_ar]`}
                                        placeholder={t('form.rule_ar')}
                                        dir="rtl"
                                        defaultValue={rule.body_ar}
                                        required
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('form.remove_rule', {
                                        n: index + 1,
                                    })}
                                    onClick={() =>
                                        setRules(
                                            rules.filter((_, i) => i !== index),
                                        )
                                    }
                                >
                                    <Trash2 />
                                </Button>
                            </div>
                        ))}
                    </section>

                    <section className="space-y-4">
                        <div className="flex items-center justify-between gap-3">
                            <div>
                                <h3 className="text-sm font-medium">
                                    {t('form.perks')}
                                </h3>
                                <p className="text-xs text-muted-foreground">
                                    {t('form.perks_hint')}
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="cursor-pointer"
                                onClick={() =>
                                    setPerks([
                                        ...perks,
                                        { body_ar: '', body_en: '' },
                                    ])
                                }
                            >
                                <Plus />
                                {t('form.add_perk')}
                            </Button>
                        </div>

                        {perks.map((perk, index) => (
                            <div key={index} className="flex items-end gap-2">
                                <div className="grid flex-1 gap-2 sm:grid-cols-2">
                                    <Input
                                        name={`perks[${index}][body_en]`}
                                        placeholder={t('form.perk_en')}
                                        dir="ltr"
                                        defaultValue={perk.body_en}
                                        required
                                    />
                                    <Input
                                        name={`perks[${index}][body_ar]`}
                                        placeholder={t('form.perk_ar')}
                                        dir="rtl"
                                        defaultValue={perk.body_ar}
                                        required
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="cursor-pointer"
                                    aria-label={t('form.remove_perk', {
                                        n: index + 1,
                                    })}
                                    onClick={() =>
                                        setPerks(
                                            perks.filter((_, i) => i !== index),
                                        )
                                    }
                                >
                                    <Trash2 />
                                </Button>
                            </div>
                        ))}
                    </section>

                    <Button type="submit" disabled={processing}>
                        {submitLabel}
                    </Button>
                </>
            )}
        </Form>
    );
}
