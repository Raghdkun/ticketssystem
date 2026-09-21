import { Form } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { FormSection } from '@/components/owner/form-section';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { localised, useLocale } from '@/lib/locale';
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
    /** Null means no seat limit. */
    total_quantity: number | null;
    max_per_appointment: number;
    hold_hours: number;
    starts_at: string;
    ends_at: string | null;
    appointments_close_at: string;
    status: string;
    is_unlisted?: boolean;
    auto_confirm?: boolean;
    location_id?: number | null;
    cover?: string | null;
    rules: EventRule[];
    perks: EventPerk[];
};

type Props = {
    /** Wayfinder form props, e.g. EventController.store.form() */
    action: Record<string, unknown>;
    values?: Partial<EventFormValues>;
    submitLabel: string;
    locations?: LocationOption[];
};

export type LocationOption = {
    id: number;
    name_ar: string;
    name_en: string;
    is_primary: boolean;
};

const CADENCES = ['daily', 'weekly', 'fortnightly', 'monthly'] as const;

const SELECT_CLASS =
    'min-h-11 rounded-md border border-input bg-input-background px-3 text-sm';

/**
 * Field wrapper: label, control, and its validation message.
 *
 * A required field says so on the label -- an asterisk for sighted readers,
 * the word for a screen reader -- and the legend at the top of the form
 * explains the mark once.
 */
function Field({
    id,
    label,
    error,
    children,
    hint,
    required = false,
}: {
    id: string;
    label: string;
    error?: string;
    children: React.ReactNode;
    hint?: string;
    required?: boolean;
}) {
    const t = useTranslation();

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>
                {label}
                {required && (
                    <>
                        <span aria-hidden="true" className="text-primary-text">
                            {' '}
                            *
                        </span>
                        <span className="sr-only">
                            {' '}
                            ({t('form.required_mark')})
                        </span>
                    </>
                )}
            </Label>
            {children}
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
            <InputError message={error} />
        </div>
    );
}

/**
 * A checkbox with its own label and explanation, as one tap target.
 *
 * Radix checkboxes submit through a hidden input when given a name, so the
 * server reads these with boolean(): present when ticked, absent when not.
 */
function Option({
    id,
    name,
    label,
    hint,
    checked,
    onCheckedChange,
}: {
    id: string;
    name: string;
    label: string;
    hint: string;
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
}) {
    return (
        <label
            htmlFor={id}
            className="flex min-h-11 cursor-pointer items-start gap-3 rounded-lg border p-3 transition-colors hover:bg-muted/50"
        >
            <Checkbox
                id={id}
                name={name}
                value="1"
                checked={checked}
                onCheckedChange={(value) => onCheckedChange(value === true)}
                className="mt-0.5 cursor-pointer"
            />
            <span className="grid gap-0.5">
                <span className="text-sm font-medium">{label}</span>
                <span className="text-xs text-muted-foreground">{hint}</span>
            </span>
        </label>
    );
}

export default function EventForm({
    action,
    values,
    submitLabel,
    locations = [],
}: Props) {
    const t = useTranslation();
    const { locale } = useLocale();
    const [rules, setRules] = useState<EventRule[]>(values?.rules ?? []);
    const [perks, setPerks] = useState<EventPerk[]>(values?.perks ?? []);

    // The options that change what the rest of the form shows.
    const [unlimited, setUnlimited] = useState(values?.total_quantity === null);
    const [autoConfirm, setAutoConfirm] = useState(
        values?.auto_confirm ?? false,
    );
    const [unlisted, setUnlisted] = useState(values?.is_unlisted ?? false);
    const [removeCover, setRemoveCover] = useState(false);
    const [price, setPrice] = useState(String(values?.price ?? 0));
    const [status, setStatus] = useState(values?.status ?? 'draft');

    const isFree = Number(price) === 0;

    return (
        <Form
            {...action}
            options={{ preserveScroll: true }}
            className="space-y-8"
            encType="multipart/form-data"
        >
            {({ processing, errors }) => (
                <>
                    <p className="text-xs text-muted-foreground">
                        <span aria-hidden="true" className="text-primary-text">
                            *
                        </span>{' '}
                        {t('form.required_legend')}
                    </p>

                    <FormSection
                        title={t('form.section.details')}
                        hint={t('form.section.details_hint')}
                        defaultOpen
                    >
                        <section className="grid gap-4 sm:grid-cols-2">
                            <Field
                                id="title_en"
                                label={t('form.title_en')}
                                error={errors.title_en}
                                required
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
                                required
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
                    </FormSection>

                    <FormSection
                        title={t('form.section.tickets')}
                        hint={t('form.section.tickets_hint')}
                        defaultOpen
                    >
                        <section className="grid gap-4 sm:grid-cols-3">
                            <Field
                                id="price"
                                label={t('form.price')}
                                error={errors.price}
                                hint={t('form.price_hint')}
                                required
                            >
                                <Input
                                    id="price"
                                    name="price"
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    required
                                    value={price}
                                    onChange={(e) => setPrice(e.target.value)}
                                />
                            </Field>

                            <Field
                                id="currency"
                                label={t('form.currency')}
                                error={errors.currency}
                                required
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
                                required={!unlimited}
                            >
                                {/* Kept mounted but disabled when unlimited: a
                                    disabled control is not submitted, and the
                                    typed count comes back if the box is
                                    unticked again. */}
                                <Input
                                    id="total_quantity"
                                    name="total_quantity"
                                    type="number"
                                    min={1}
                                    required={!unlimited}
                                    disabled={unlimited}
                                    defaultValue={values?.total_quantity ?? 100}
                                />
                            </Field>

                            <div className="sm:col-span-3">
                                <Option
                                    id="unlimited"
                                    name="unlimited"
                                    label={t('form.unlimited')}
                                    hint={t('form.unlimited_hint')}
                                    checked={unlimited}
                                    onCheckedChange={setUnlimited}
                                />
                            </div>

                            <Field
                                id="max_per_appointment"
                                label={t('form.max_per')}
                                error={errors.max_per_appointment}
                                required
                            >
                                <Input
                                    id="max_per_appointment"
                                    name="max_per_appointment"
                                    type="number"
                                    min={1}
                                    max={50}
                                    required
                                    defaultValue={
                                        values?.max_per_appointment ?? 10
                                    }
                                />
                            </Field>

                            <Field
                                id="hold_hours"
                                label={t('form.hold_hours')}
                                error={errors.hold_hours}
                                hint={t('form.hold_hint')}
                                required
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
                                hint={
                                    status === 'archived'
                                        ? t('form.archived_note')
                                        : undefined
                                }
                                required
                            >
                                <select
                                    id="status"
                                    name="status"
                                    value={status}
                                    onChange={(e) => setStatus(e.target.value)}
                                    className={SELECT_CLASS}
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

                            <Field
                                id="location_id"
                                label={t('location.pick')}
                                error={errors.location_id}
                            >
                                <select
                                    id="location_id"
                                    name="location_id"
                                    defaultValue={values?.location_id ?? ''}
                                    className={SELECT_CLASS}
                                >
                                    {/* Empty means "wherever the venue defaults
                                        to", which is what an owner with a single
                                        location wants and never has to think about. */}
                                    <option value="">
                                        {t('location.use_default')}
                                    </option>
                                    {locations.map((location) => (
                                        <option
                                            key={location.id}
                                            value={location.id}
                                        >
                                            {localised(
                                                locale,
                                                location.name_ar,
                                                location.name_en,
                                            )}
                                        </option>
                                    ))}
                                </select>
                            </Field>

                            <div className="grid gap-3 sm:col-span-3">
                                <Option
                                    id="is_unlisted"
                                    name="is_unlisted"
                                    label={t('form.unlisted')}
                                    hint={t('form.unlisted_hint')}
                                    checked={unlisted}
                                    onCheckedChange={setUnlisted}
                                />

                                {/* Only a free event can confirm on the spot:
                                    there is nothing to pay. The choice is kept
                                    server-side if the price changes, so the box
                                    simply comes back when the price is 0 again. */}
                                {isFree && (
                                    <Option
                                        id="auto_confirm"
                                        name="auto_confirm"
                                        label={t('form.auto_confirm')}
                                        hint={t('form.auto_confirm_hint')}
                                        checked={autoConfirm}
                                        onCheckedChange={setAutoConfirm}
                                    />
                                )}
                            </div>
                        </section>
                    </FormSection>

                    <FormSection
                        title={t('form.section.when')}
                        hint={t('form.section.when_hint')}
                        defaultOpen
                    >
                        <section className="grid gap-4 sm:grid-cols-3">
                            <Field
                                id="starts_at"
                                label={t('form.starts_at')}
                                error={errors.starts_at}
                                required
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
                                required
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
                    </FormSection>

                    <FormSection
                        title={t('form.section.cover')}
                        hint={t('form.section.cover_hint')}
                    >
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
                                <>
                                    <img
                                        src={`/storage/${values.cover}`}
                                        alt={t('form.cover')}
                                        className={
                                            removeCover
                                                ? 'aspect-video w-full max-w-sm rounded-lg object-cover opacity-40 grayscale'
                                                : 'aspect-video w-full max-w-sm rounded-lg object-cover'
                                        }
                                    />

                                    <label
                                        htmlFor="remove_cover"
                                        className="flex min-h-11 w-fit cursor-pointer items-center gap-3 text-sm"
                                    >
                                        <Checkbox
                                            id="remove_cover"
                                            name="remove_cover"
                                            value="1"
                                            checked={removeCover}
                                            onCheckedChange={(value) =>
                                                setRemoveCover(value === true)
                                            }
                                            className="cursor-pointer"
                                        />
                                        {t('form.remove_cover')}
                                    </label>
                                </>
                            )}
                        </section>
                    </FormSection>

                    <FormSection
                        title={t('form.section.rules')}
                        hint={t('form.section.rules_hint')}
                        badge={rules.length}
                    >
                        <section className="space-y-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <h3 className="text-sm font-medium">
                                        {t('form.rules_title')}
                                    </h3>
                                    <p className="text-xs text-muted-foreground">
                                        {t('form.rules_hint')}
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
                                <div
                                    key={index}
                                    className="flex items-end gap-2"
                                >
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
                                                rules.filter(
                                                    (_, i) => i !== index,
                                                ),
                                            )
                                        }
                                    >
                                        <Trash2 />
                                    </Button>
                                </div>
                            ))}
                        </section>
                    </FormSection>

                    <FormSection
                        title={t('form.section.perks')}
                        hint={t('form.section.perks_hint')}
                        badge={perks.length}
                    >
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
                                <div
                                    key={index}
                                    className="flex items-end gap-2"
                                >
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
                                                perks.filter(
                                                    (_, i) => i !== index,
                                                ),
                                            )
                                        }
                                    >
                                        <Trash2 />
                                    </Button>
                                </div>
                            ))}
                        </section>
                    </FormSection>

                    {/* Copies forward on a cadence, in the same save. The
                        select starts on "do not repeat", so a routine edit
                        never makes copies by accident. */}
                    <FormSection
                        title={t('form.section_repeat')}
                        hint={t('form.section_repeat_hint')}
                    >
                        <section className="grid gap-4 sm:grid-cols-2">
                            <Field
                                id="repeat_cadence"
                                label={t('owner.repeat_cadence')}
                                error={errors.repeat_cadence}
                            >
                                <select
                                    id="repeat_cadence"
                                    name="repeat_cadence"
                                    defaultValue=""
                                    className={SELECT_CLASS}
                                >
                                    <option value="">
                                        {t('form.repeat_none')}
                                    </option>
                                    {CADENCES.map((key) => (
                                        <option key={key} value={key}>
                                            {t(`owner.cadence.${key}`)}
                                        </option>
                                    ))}
                                </select>
                            </Field>

                            <Field
                                id="repeat_count"
                                label={t('owner.repeat_count')}
                                error={errors.repeat_count}
                            >
                                <Input
                                    id="repeat_count"
                                    name="repeat_count"
                                    type="number"
                                    min={1}
                                    max={12}
                                    defaultValue={4}
                                />
                            </Field>

                            <p className="text-xs text-muted-foreground sm:col-span-2">
                                {t('form.repeat_note')}
                            </p>
                        </section>
                    </FormSection>

                    <Button type="submit" disabled={processing}>
                        {submitLabel}
                    </Button>
                </>
            )}
        </Form>
    );
}
