import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { localised, useLocale } from '@/lib/locale';
import { useTranslation } from '@/lib/translation';
import type { Offer } from './offer-summary';

export type PlaceOption = {
    id: number;
    slug: string;
    name_ar: string;
    name_en: string;
};

export const SELECT_CLASS =
    'min-h-11 w-full rounded-md border border-input bg-input-background px-3 text-sm';

/**
 * The fields of a commercial offer, for drafting and for editing a draft.
 * The venue is fixed once the offer exists.
 */
export function OfferFields({
    offer,
    places,
    feeTypes,
    feePayers,
    errors,
}: {
    offer?: Offer;
    places: PlaceOption[];
    feeTypes: string[];
    feePayers: string[];
    errors: Record<string, string | undefined>;
}) {
    const t = useTranslation();
    const { locale } = useLocale();

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <div className="grid gap-2 sm:col-span-2">
                <Label htmlFor="place_id">{t('commercial.admin.venue')}</Label>
                <select
                    id="place_id"
                    name="place_id"
                    required
                    disabled={offer !== undefined}
                    defaultValue={
                        offer
                            ? (places.find((p) => p.slug === offer.place.slug)
                                  ?.id ?? '')
                            : ''
                    }
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
                {/* A disabled select is not submitted; the venue rides along
                    as a hidden field on an existing offer. */}
                {offer && (
                    <input
                        type="hidden"
                        name="place_id"
                        value={
                            places.find((p) => p.slug === offer.place.slug)
                                ?.id ?? ''
                        }
                    />
                )}
                <InputError message={errors.place_id} />
            </div>

            <Field
                id="title_ar"
                label={t('agreement.admin.title_ar')}
                error={errors.title_ar}
            >
                <Input
                    id="title_ar"
                    name="title_ar"
                    dir="rtl"
                    required
                    defaultValue={offer?.title_ar ?? 'العرض التجاري'}
                />
            </Field>
            <Field
                id="title_en"
                label={t('agreement.admin.title_en')}
                error={errors.title_en}
            >
                <Input
                    id="title_en"
                    name="title_en"
                    dir="ltr"
                    required
                    defaultValue={offer?.title_en ?? 'Commercial offer'}
                />
            </Field>

            <Field
                id="fee_type"
                label={t('commercial.admin.fee_type')}
                error={errors.fee_type}
            >
                <select
                    id="fee_type"
                    name="fee_type"
                    required
                    defaultValue={offer?.fee_type ?? 'percentage'}
                    className={SELECT_CLASS}
                >
                    {feeTypes.map((type) => (
                        <option key={type} value={type}>
                            {t(`commercial.fee_types.${type}`)}
                        </option>
                    ))}
                </select>
            </Field>
            <Field
                id="fee_value"
                label={t('commercial.admin.fee_value')}
                error={errors.fee_value}
            >
                <Input
                    id="fee_value"
                    name="fee_value"
                    type="number"
                    step="0.01"
                    min={0}
                    dir="ltr"
                    defaultValue={offer?.fee_value ?? ''}
                />
            </Field>

            <Field
                id="fee_payer"
                label={t('commercial.admin.fee_payer')}
                error={errors.fee_payer}
            >
                <select
                    id="fee_payer"
                    name="fee_payer"
                    required
                    defaultValue={offer?.fee_payer ?? 'customer'}
                    className={SELECT_CLASS}
                >
                    {feePayers.map((payer) => (
                        <option key={payer} value={payer}>
                            {t(`commercial.fee_payers.${payer}`)}
                        </option>
                    ))}
                </select>
            </Field>
            <Field
                id="settlement_days"
                label={t('commercial.admin.settlement_days')}
                error={errors.settlement_days}
            >
                <Input
                    id="settlement_days"
                    name="settlement_days"
                    type="number"
                    min={0}
                    max={365}
                    dir="ltr"
                    defaultValue={offer?.settlement_days ?? ''}
                />
            </Field>

            <Field
                id="subscription_amount"
                label={t('commercial.admin.subscription_amount')}
                error={errors.subscription_amount}
            >
                <Input
                    id="subscription_amount"
                    name="subscription_amount"
                    type="number"
                    step="0.01"
                    min={0}
                    dir="ltr"
                    defaultValue={offer?.subscription_amount ?? ''}
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
                    defaultValue={offer?.currency ?? 'SYP'}
                />
            </Field>

            <Field
                id="valid_from"
                label={t('commercial.admin.valid_from')}
                error={errors.valid_from}
            >
                <Input
                    id="valid_from"
                    name="valid_from"
                    type="date"
                    defaultValue={offer?.valid_from ?? ''}
                />
            </Field>
            <Field
                id="valid_until"
                label={t('commercial.admin.valid_until')}
                error={errors.valid_until}
            >
                <Input
                    id="valid_until"
                    name="valid_until"
                    type="date"
                    defaultValue={offer?.valid_until ?? ''}
                />
            </Field>

            <Field
                id="included_services_ar"
                label={t('commercial.admin.included_ar')}
                error={errors.included_services_ar}
            >
                <Textarea
                    id="included_services_ar"
                    name="included_services_ar"
                    dir="rtl"
                    rows={3}
                    defaultValue={offer?.included_services_ar ?? ''}
                />
            </Field>
            <Field
                id="included_services_en"
                label={t('commercial.admin.included_en')}
                error={errors.included_services_en}
            >
                <Textarea
                    id="included_services_en"
                    name="included_services_en"
                    dir="ltr"
                    rows={3}
                    defaultValue={offer?.included_services_en ?? ''}
                />
            </Field>

            <Field
                id="additional_terms_ar"
                label={t('commercial.admin.additional_ar')}
                error={errors.additional_terms_ar}
            >
                <Textarea
                    id="additional_terms_ar"
                    name="additional_terms_ar"
                    dir="rtl"
                    rows={5}
                    defaultValue={offer?.additional_terms_ar ?? ''}
                />
            </Field>
            <Field
                id="additional_terms_en"
                label={t('commercial.admin.additional_en')}
                error={errors.additional_terms_en}
            >
                <Textarea
                    id="additional_terms_en"
                    name="additional_terms_en"
                    dir="ltr"
                    rows={5}
                    defaultValue={offer?.additional_terms_en ?? ''}
                />
            </Field>
        </div>
    );
}

export function Field({
    id,
    label,
    error,
    children,
}: {
    id: string;
    label: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
