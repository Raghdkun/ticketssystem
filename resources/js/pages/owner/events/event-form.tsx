import { Form } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

export type EventRule = { body_ar: string; body_en: string };

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
    theme_mode: string;
    primary_color: string | null;
    secondary_color: string | null;
    cover?: string | null;
    rules: EventRule[];
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
    const [rules, setRules] = useState<EventRule[]>(values?.rules ?? []);
    const [themeMode, setThemeMode] = useState(values?.theme_mode ?? 'auto');

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
                            label="Title (English)"
                            error={errors.title_en}
                        >
                            <Input
                                id="title_en"
                                name="title_en"
                                required
                                defaultValue={values?.title_en}
                            />
                        </Field>

                        <Field
                            id="title_ar"
                            label="Title (Arabic)"
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
                            label="Description (English)"
                            error={errors.description_en}
                        >
                            <Textarea
                                id="description_en"
                                name="description_en"
                                defaultValue={values?.description_en ?? ''}
                            />
                        </Field>

                        <Field
                            id="description_ar"
                            label="Description (Arabic)"
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
                            label="Price"
                            error={errors.price}
                            hint="Set 0 to make the event free."
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
                            label="Currency"
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
                            label="Total seats"
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
                            label="Max per appointment"
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
                            label="Hold window (hours)"
                            error={errors.hold_hours}
                            hint="Unpaid reservations expire after this and free their seats."
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

                        <Field id="status" label="Status" error={errors.status}>
                            <select
                                id="status"
                                name="status"
                                defaultValue={values?.status ?? 'draft'}
                                className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                            >
                                <option value="draft">Draft</option>
                                <option value="published">Published</option>
                                <option value="archived">Archived</option>
                            </select>
                        </Field>
                    </section>

                    <section className="grid gap-4 sm:grid-cols-3">
                        <Field
                            id="starts_at"
                            label="Starts at"
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
                            label="Ends at"
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
                            label="Appointments close at"
                            error={errors.appointments_close_at}
                            hint="After this, the event stops accepting appointments."
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
                            label="Cover image"
                            error={errors.cover}
                            hint="JPEG, PNG or WebP, up to 8 MB. Resized to WebP automatically."
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
                                alt="Current cover"
                                className="aspect-video w-full max-w-sm rounded-lg object-cover"
                            />
                        )}

                        <Field
                            id="theme_mode"
                            label="Theme colours"
                            error={errors.theme_mode}
                        >
                            <select
                                id="theme_mode"
                                name="theme_mode"
                                value={themeMode}
                                onChange={(e) => setThemeMode(e.target.value)}
                                className="h-9 max-w-xs rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                            >
                                <option value="auto">
                                    Auto — from cover image
                                </option>
                                <option value="manual">
                                    Manual — pick my own
                                </option>
                            </select>
                        </Field>

                        {themeMode === 'manual' && (
                            <div className="grid max-w-sm gap-4 sm:grid-cols-2">
                                <Field
                                    id="primary_color"
                                    label="Primary"
                                    error={errors.primary_color}
                                >
                                    <Input
                                        id="primary_color"
                                        name="primary_color"
                                        type="color"
                                        defaultValue={
                                            values?.primary_color ?? '#6d28d9'
                                        }
                                    />
                                </Field>
                                <Field
                                    id="secondary_color"
                                    label="Secondary"
                                    error={errors.secondary_color}
                                >
                                    <Input
                                        id="secondary_color"
                                        name="secondary_color"
                                        type="color"
                                        defaultValue={
                                            values?.secondary_color ?? '#db2777'
                                        }
                                    />
                                </Field>
                            </div>
                        )}
                    </section>

                    <section className="space-y-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <h3 className="text-sm font-medium">
                                    Rules &amp; notes
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
                                Add rule
                            </Button>
                        </div>

                        {rules.map((rule, index) => (
                            <div key={index} className="flex items-end gap-2">
                                <div className="grid flex-1 gap-2 sm:grid-cols-2">
                                    <Input
                                        name={`rules[${index}][body_en]`}
                                        placeholder="e.g. +18 only"
                                        defaultValue={rule.body_en}
                                        required
                                    />
                                    <Input
                                        name={`rules[${index}][body_ar]`}
                                        placeholder="مثال: للأعمار فوق ١٨"
                                        dir="rtl"
                                        defaultValue={rule.body_ar}
                                        required
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Remove rule ${index + 1}`}
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

                    <Button type="submit" disabled={processing}>
                        {submitLabel}
                    </Button>
                </>
            )}
        </Form>
    );
}
