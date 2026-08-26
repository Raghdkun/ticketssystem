import { Form, Head } from '@inertiajs/react';
import { Save, Store } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import type { LatLng } from '@/components/map/map-canvas';
import { MapPicker } from '@/components/map/map-picker';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/lib/translation';
import placeRoute from '@/routes/owner/place';

type Place = {
    name_ar: string;
    name_en: string;
    whatsapp_number: string | null;
    latitude: number | null;
    longitude: number | null;
    address_ar: string | null;
    address_en: string | null;
    landmark_ar: string | null;
    landmark_en: string | null;
};

export default function OwnerPlace({ place }: { place: Place | null }) {
    const t = useTranslation();

    const [pin, setPin] = useState<LatLng | null>(
        place?.latitude != null && place?.longitude != null
            ? { lat: place.latitude, lng: place.longitude }
            : null,
    );

    if (!place) {
        return (
            <>
                <Head title={t('owner.place')} />
                <div className="p-4">
                    <EmptyState icon={Store} title={t('dash.no_place')} />
                </div>
            </>
        );
    }

    return (
        <>
            <Head title={t('owner.place')} />

            <div className="space-y-8 p-4">
                <Heading
                    variant="small"
                    title={t('owner.place')}
                    description={t('owner.place_sub')}
                />

                <Form
                    action="/owner/place"
                    method="patch"
                    options={{ preserveScroll: true }}
                    className="space-y-8"
                >
                    {({ processing, errors }) => (
                        <>
                            <section className="space-y-4 rounded-xl border p-4 sm:p-6">
                                <h2 className="text-sm font-medium">
                                    {t('owner.place_details')}
                                </h2>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="name_ar">
                                            {t('owner.name_ar')}
                                        </Label>
                                        <Input
                                            id="name_ar"
                                            name="name_ar"
                                            defaultValue={place.name_ar}
                                            dir="rtl"
                                            required
                                        />
                                        <InputError message={errors.name_ar} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="name_en">
                                            {t('owner.name_en')}
                                        </Label>
                                        <Input
                                            id="name_en"
                                            name="name_en"
                                            defaultValue={place.name_en}
                                            dir="ltr"
                                            required
                                        />
                                        <InputError message={errors.name_en} />
                                    </div>

                                    <div className="grid gap-2 sm:col-span-2">
                                        <Label htmlFor="whatsapp_number">
                                            {t('owner.whatsapp')}
                                        </Label>
                                        <Input
                                            id="whatsapp_number"
                                            name="whatsapp_number"
                                            defaultValue={
                                                place.whatsapp_number ?? ''
                                            }
                                            dir="ltr"
                                            placeholder="09XXXXXXXX"
                                        />
                                        <InputError
                                            message={errors.whatsapp_number}
                                        />
                                    </div>
                                </div>
                            </section>

                            <section className="space-y-4 rounded-xl border p-4 sm:p-6">
                                <div>
                                    <h2 className="text-sm font-medium">
                                        {t('owner.place_location')}
                                    </h2>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {t('owner.place_location_hint')}
                                    </p>
                                </div>

                                <MapPicker value={pin} onChange={setPin} />

                                {/* The map is a control, not a form field, so
                                    the coordinate posts through hidden inputs.
                                    Empty strings clear the pin server-side. */}
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
                                <InputError message={errors.longitude} />

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="address_ar">
                                            {t('place.address_ar')}
                                        </Label>
                                        <Input
                                            id="address_ar"
                                            name="address_ar"
                                            defaultValue={
                                                place.address_ar ?? ''
                                            }
                                            dir="rtl"
                                        />
                                        <InputError
                                            message={errors.address_ar}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="address_en">
                                            {t('place.address_en')}
                                        </Label>
                                        <Input
                                            id="address_en"
                                            name="address_en"
                                            defaultValue={
                                                place.address_en ?? ''
                                            }
                                            dir="ltr"
                                        />
                                        <InputError
                                            message={errors.address_en}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="landmark_ar">
                                            {t('place.landmark_ar')}
                                        </Label>
                                        <Input
                                            id="landmark_ar"
                                            name="landmark_ar"
                                            defaultValue={
                                                place.landmark_ar ?? ''
                                            }
                                            dir="rtl"
                                            placeholder={t(
                                                'place.landmark_placeholder',
                                            )}
                                        />
                                        <InputError
                                            message={errors.landmark_ar}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="landmark_en">
                                            {t('place.landmark_en')}
                                        </Label>
                                        <Input
                                            id="landmark_en"
                                            name="landmark_en"
                                            defaultValue={
                                                place.landmark_en ?? ''
                                            }
                                            dir="ltr"
                                        />
                                        <InputError
                                            message={errors.landmark_en}
                                        />
                                    </div>
                                </div>
                            </section>

                            <Button
                                type="submit"
                                disabled={processing}
                                className="cursor-pointer"
                            >
                                {processing ? <Spinner /> : <Save />}
                                {t('common.save')}
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

OwnerPlace.layout = {
    breadcrumbs: [{ title: 'owner.place', href: placeRoute.edit() }],
};
