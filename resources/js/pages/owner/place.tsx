import { Form, Head, Link } from '@inertiajs/react';
import { MapPin, Save, Store } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/lib/translation';
import locationsRoute from '@/routes/owner/locations';
import placeRoute from '@/routes/owner/place';

type Place = {
    name_ar: string;
    name_en: string;
    whatsapp_number: string | null;
};

export default function OwnerPlace({ place }: { place: Place | null }) {
    const t = useTranslation();

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

                            <section className="space-y-3 rounded-xl border p-4 sm:p-6">
                                <h2 className="text-sm font-medium">
                                    {t('owner.place_location')}
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    {t('owner.place_location_moved')}
                                </p>
                                <Button asChild variant="outline">
                                    <Link href={locationsRoute.index()}>
                                        <MapPin />
                                        {t('owner.locations')}
                                    </Link>
                                </Button>
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
