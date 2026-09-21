import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { localised, useLocale } from '@/lib/locale';
import { useTranslation } from '@/lib/translation';
import { Field, SELECT_CLASS } from './offer-fields';
import type { PlaceOption } from './offer-fields';
import type { Order } from './order-summary';

export type EventOption = { id: number; title_ar: string; title_en: string };

/**
 * The fields of a service order, for drafting and for editing a draft.
 */
export function OrderFields({
    order,
    places,
    events,
    types,
    errors,
}: {
    order?: Order;
    places: PlaceOption[];
    /** The venue's events; empty on a new order until it is saved. */
    events: EventOption[];
    types: string[];
    errors: Record<string, string | undefined>;
}) {
    const t = useTranslation();
    const { locale } = useLocale();
    const placeId = order
        ? (places.find((p) => p.slug === order.place.slug)?.id ?? '')
        : '';

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <Field
                id="place_id"
                label={t('orders.admin.venue')}
                error={errors.place_id}
            >
                <select
                    id="place_id"
                    name="place_id"
                    required
                    disabled={order !== undefined}
                    defaultValue={placeId}
                    className={SELECT_CLASS}
                >
                    <option value="" disabled>
                        —
                    </option>
                    {places.map((place) => (
                        <option key={place.id} value={place.id}>
                            {localised(locale, place.name_ar, place.name_en)}
                        </option>
                    ))}
                </select>
                {order && (
                    <input type="hidden" name="place_id" value={placeId} />
                )}
            </Field>

            <Field
                id="event_id"
                label={t('orders.admin.event')}
                error={errors.event_id}
            >
                <select
                    id="event_id"
                    name="event_id"
                    defaultValue={order?.event?.id ?? ''}
                    className={SELECT_CLASS}
                >
                    <option value="">{t('orders.admin.no_event')}</option>
                    {events.map((event) => (
                        <option key={event.id} value={event.id}>
                            {localised(locale, event.title_ar, event.title_en)}
                        </option>
                    ))}
                </select>
            </Field>

            <Field
                id="service_type"
                label={t('orders.type')}
                error={errors.service_type}
            >
                <select
                    id="service_type"
                    name="service_type"
                    required
                    defaultValue={order?.service_type ?? 'door_staff'}
                    className={SELECT_CLASS}
                >
                    {types.map((type) => (
                        <option key={type} value={type}>
                            {t(`orders.types.${type}`)}
                        </option>
                    ))}
                </select>
            </Field>
            <Field
                id="quantity"
                label={t('orders.quantity')}
                error={errors.quantity}
            >
                <Input
                    id="quantity"
                    name="quantity"
                    type="number"
                    min={1}
                    required
                    dir="ltr"
                    defaultValue={order?.quantity ?? 1}
                />
            </Field>

            <Field
                id="title_ar"
                label={t('orders.admin.title_ar')}
                error={errors.title_ar}
            >
                <Input
                    id="title_ar"
                    name="title_ar"
                    dir="rtl"
                    required
                    defaultValue={order?.title_ar ?? ''}
                />
            </Field>
            <Field
                id="title_en"
                label={t('orders.admin.title_en')}
                error={errors.title_en}
            >
                <Input
                    id="title_en"
                    name="title_en"
                    dir="ltr"
                    required
                    defaultValue={order?.title_en ?? ''}
                />
            </Field>

            <Field
                id="unit_price"
                label={t('orders.unit_price')}
                error={errors.unit_price}
            >
                <Input
                    id="unit_price"
                    name="unit_price"
                    type="number"
                    step="0.01"
                    min={0}
                    dir="ltr"
                    defaultValue={order?.unit_price ?? ''}
                />
            </Field>
            <Field
                id="currency"
                label={t('commercial.admin.currency')}
                error={errors.currency}
            >
                <Input
                    id="currency"
                    name="currency"
                    maxLength={3}
                    dir="ltr"
                    defaultValue={order?.currency ?? 'SYP'}
                />
            </Field>

            <Field
                id="description_ar"
                label={t('orders.admin.description_ar')}
                error={errors.description_ar}
            >
                <Textarea
                    id="description_ar"
                    name="description_ar"
                    dir="rtl"
                    rows={4}
                    defaultValue={order?.description_ar ?? ''}
                />
            </Field>
            <Field
                id="description_en"
                label={t('orders.admin.description_en')}
                error={errors.description_en}
            >
                <Textarea
                    id="description_en"
                    name="description_en"
                    dir="ltr"
                    rows={4}
                    defaultValue={order?.description_en ?? ''}
                />
            </Field>

            <Field
                id="terms_ar"
                label={t('orders.admin.terms_ar')}
                error={errors.terms_ar}
            >
                <Textarea
                    id="terms_ar"
                    name="terms_ar"
                    dir="rtl"
                    rows={4}
                    defaultValue={order?.terms_ar ?? ''}
                />
            </Field>
            <Field
                id="terms_en"
                label={t('orders.admin.terms_en')}
                error={errors.terms_en}
            >
                <Textarea
                    id="terms_en"
                    name="terms_en"
                    dir="ltr"
                    rows={4}
                    defaultValue={order?.terms_en ?? ''}
                />
            </Field>
        </div>
    );
}
