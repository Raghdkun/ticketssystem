import { Form, Head, router } from '@inertiajs/react';
import { MapPin, Plus, Save, Star, Store, Trash2, Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import Heading from '@/components/heading';
import { ImageSlider, ImageSliderEmpty } from '@/components/image-slider';
import InputError from '@/components/input-error';
import type { LatLng } from '@/components/map/map-canvas';
import { MapPicker } from '@/components/map/map-picker';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { localised, useLocale } from '@/lib/locale';
import { useTranslation } from '@/lib/translation';
import locationsRoute from '@/routes/owner/locations';

type LocationImage = { id: number; url: string };

type Location = {
    id: number;
    name_ar: string;
    name_en: string;
    latitude: number | null;
    longitude: number | null;
    address_ar: string | null;
    address_en: string | null;
    landmark_ar: string | null;
    landmark_en: string | null;
    is_primary: boolean;
    images: LocationImage[];
};

type Props = { hasPlace: boolean; locations: Location[] };

const BLANK: Omit<Location, 'id' | 'images'> = {
    name_ar: '',
    name_en: '',
    latitude: null,
    longitude: null,
    address_ar: '',
    address_en: '',
    landmark_ar: '',
    landmark_en: '',
    is_primary: false,
};

export default function OwnerLocations({ hasPlace, locations }: Props) {
    const t = useTranslation();
    const { locale } = useLocale();

    // null = nothing open, 'new' = the create form, a number = editing that one.
    const [editing, setEditing] = useState<number | 'new' | null>(null);

    if (!hasPlace) {
        return (
            <>
                <Head title={t('owner.locations')} />
                <div className="p-4">
                    <EmptyState icon={Store} title={t('dash.no_place')} />
                </div>
            </>
        );
    }

    return (
        <>
            <Head title={t('owner.locations')} />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        variant="small"
                        title={t('owner.locations')}
                        description={t('owner.locations_sub')}
                    />

                    <Button
                        onClick={() =>
                            setEditing(editing === 'new' ? null : 'new')
                        }
                        aria-expanded={editing === 'new'}
                        className="cursor-pointer"
                    >
                        <Plus />
                        {t('location.add')}
                    </Button>
                </div>

                {editing === 'new' && (
                    <LocationForm
                        action="/owner/locations"
                        method="post"
                        values={BLANK}
                        onDone={() => setEditing(null)}
                    />
                )}

                {locations.length === 0 && editing !== 'new' ? (
                    <EmptyState icon={MapPin} title={t('location.none')} />
                ) : (
                    <ul className="grid gap-4 lg:grid-cols-2">
                        {locations.map((location) => (
                            <li key={location.id}>
                                <article className="brand-surface flex h-full flex-col gap-3 rounded-xl border p-4">
                                    {location.images.length > 0 ? (
                                        <ImageSlider
                                            images={location.images.map(
                                                (i) => i.url,
                                            )}
                                            alt={localised(
                                                locale,
                                                location.name_ar,
                                                location.name_en,
                                            )}
                                        />
                                    ) : (
                                        <ImageSliderEmpty
                                            label={t('location.no_images')}
                                        />
                                    )}

                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <p className="flex items-center gap-1.5 font-semibold">
                                                {localised(
                                                    locale,
                                                    location.name_ar,
                                                    location.name_en,
                                                )}
                                                {location.is_primary && (
                                                    <span
                                                        title={t(
                                                            'location.primary',
                                                        )}
                                                        className="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 text-[0.6875rem] font-medium text-primary"
                                                    >
                                                        <Star
                                                            className="size-3"
                                                            aria-hidden
                                                        />
                                                        {t('location.primary')}
                                                    </span>
                                                )}
                                            </p>
                                            <p className="mt-0.5 truncate text-xs text-muted-foreground">
                                                {localised(
                                                    locale,
                                                    location.address_ar ?? '',
                                                    location.address_en ?? '',
                                                ) || t('place.no_address')}
                                            </p>
                                        </div>
                                    </div>

                                    <ImageUploader location={location} />

                                    <div className="mt-auto flex flex-wrap gap-2 border-t pt-3">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            className="cursor-pointer"
                                            onClick={() =>
                                                setEditing(
                                                    editing === location.id
                                                        ? null
                                                        : location.id,
                                                )
                                            }
                                        >
                                            {t('common.edit')}
                                        </Button>

                                        {/* A venue must keep one location, and
                                            the primary is what events fall back
                                            to, so it cannot be the one removed. */}
                                        {!location.is_primary && (
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                className="cursor-pointer text-destructive hover:text-destructive"
                                                onClick={() => {
                                                    if (
                                                        confirm(
                                                            t(
                                                                'location.confirm_delete',
                                                            ),
                                                        )
                                                    ) {
                                                        router.delete(
                                                            `/owner/locations/${location.id}`,
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        );
                                                    }
                                                }}
                                            >
                                                <Trash2 />
                                                {t('common.delete')}
                                            </Button>
                                        )}
                                    </div>

                                    {editing === location.id && (
                                        <LocationForm
                                            action={`/owner/locations/${location.id}`}
                                            method="patch"
                                            values={location}
                                            onDone={() => setEditing(null)}
                                        />
                                    )}
                                </article>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

function ImageUploader({ location }: { location: Location }) {
    const t = useTranslation();
    const input = useRef<HTMLInputElement>(null);
    const [busy, setBusy] = useState(false);

    return (
        <div className="flex flex-wrap items-center gap-2">
            <input
                ref={input}
                type="file"
                accept="image/jpeg,image/png,image/webp"
                aria-label={t('location.add_image')}
                className="sr-only"
                onChange={(event) => {
                    const file = event.target.files?.[0];

                    if (!file) {
                        return;
                    }

                    setBusy(true);
                    router.post(
                        `/owner/locations/${location.id}/images`,
                        { image: file },
                        {
                            preserveScroll: true,
                            forceFormData: true,
                            onFinish: () => {
                                setBusy(false);

                                // Cleared so the same file can be picked again
                                // after a failed upload.
                                if (input.current) {
                                    input.current.value = '';
                                }
                            },
                        },
                    );
                }}
            />

            <Button
                type="button"
                size="sm"
                variant="outline"
                disabled={busy}
                className="cursor-pointer"
                onClick={() => input.current?.click()}
            >
                {busy ? <Spinner /> : <Upload />}
                {t('location.add_image')}
            </Button>

            {location.images.length > 0 && (
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    className="cursor-pointer text-muted-foreground"
                    onClick={() => {
                        const last =
                            location.images[location.images.length - 1];

                        router.delete(
                            `/owner/locations/${location.id}/images/${last.id}`,
                            { preserveScroll: true },
                        );
                    }}
                >
                    <Trash2 />
                    {t('location.remove_last_image')}
                </Button>
            )}
        </div>
    );
}

function LocationForm({
    action,
    method,
    values,
    onDone,
}: {
    action: string;
    method: 'post' | 'patch';
    values: Omit<Location, 'id' | 'images'>;
    onDone: () => void;
}) {
    const t = useTranslation();
    const [pin, setPin] = useState<LatLng | null>(
        values.latitude != null && values.longitude != null
            ? { lat: values.latitude, lng: values.longitude }
            : null,
    );

    return (
        <Form
            action={action}
            method={method}
            options={{ preserveScroll: true }}
            onSuccess={onDone}
            className="space-y-4 rounded-xl border p-4"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            id="name_ar"
                            label={t('owner.name_ar')}
                            dir="rtl"
                            defaultValue={values.name_ar}
                            error={errors.name_ar}
                            required
                        />
                        <Field
                            id="name_en"
                            label={t('owner.name_en')}
                            dir="ltr"
                            defaultValue={values.name_en}
                            error={errors.name_en}
                            required
                        />
                    </div>

                    <MapPicker value={pin} onChange={setPin} />
                    <input
                        type="hidden"
                        name="latitude"
                        value={pin?.lat ?? ''}
                    />
                    <input
                        type="hidden"
                        name="longitude"
                        value={pin?.lng ?? ''}
                    />
                    <InputError message={errors.latitude} />

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            id="address_ar"
                            label={t('place.address_ar')}
                            dir="rtl"
                            defaultValue={values.address_ar ?? ''}
                            error={errors.address_ar}
                        />
                        <Field
                            id="address_en"
                            label={t('place.address_en')}
                            dir="ltr"
                            defaultValue={values.address_en ?? ''}
                            error={errors.address_en}
                        />
                        <Field
                            id="landmark_ar"
                            label={t('place.landmark_ar')}
                            dir="rtl"
                            defaultValue={values.landmark_ar ?? ''}
                            error={errors.landmark_ar}
                            placeholder={t('place.landmark_placeholder')}
                        />
                        <Field
                            id="landmark_en"
                            label={t('place.landmark_en')}
                            dir="ltr"
                            defaultValue={values.landmark_en ?? ''}
                            error={errors.landmark_en}
                        />
                    </div>

                    <label className="flex min-h-11 cursor-pointer items-center gap-3 text-sm">
                        <input
                            type="checkbox"
                            name="is_primary"
                            value="1"
                            defaultChecked={values.is_primary}
                            className="size-5 accent-[var(--brand-jade-700)]"
                        />
                        {t('location.make_primary')}
                    </label>

                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="submit"
                            disabled={processing}
                            className="cursor-pointer"
                        >
                            {processing ? <Spinner /> : <Save />}
                            {t('common.save')}
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={onDone}
                            className="cursor-pointer"
                        >
                            {t('common.cancel')}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

function Field({
    id,
    label,
    error,
    ...props
}: React.ComponentProps<typeof Input> & {
    id: string;
    label: string;
    error?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Input id={id} name={id} {...props} />
            <InputError message={error} />
        </div>
    );
}

OwnerLocations.layout = {
    breadcrumbs: [{ title: 'owner.locations', href: locationsRoute.index() }],
};
