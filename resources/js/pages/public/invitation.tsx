import { Form, Head } from '@inertiajs/react';
import { Check, Store } from 'lucide-react';
import { useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import InputError from '@/components/input-error';
import { LanguageToggle } from '@/components/language-toggle';
import type { LatLng } from '@/components/map/map-canvas';
import { MapPicker } from '@/components/map/map-picker';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/lib/translation';

type Props = { token: string; email: string };

/**
 * Setting up a venue from an invitation.
 *
 * The whole account exists after one submit -- login, venue and first
 * location -- so it is one page rather than a wizard: an owner filling this in
 * on a phone at their own hall should not be able to get halfway and lose it.
 */
export default function AcceptInvitation({ token, email }: Props) {
    const t = useTranslation();
    const [pin, setPin] = useState<LatLng | null>(null);

    return (
        <div className="min-h-svh bg-background">
            <Head title={t('invite.title')} />

            <div
                className="h-1.5 w-full"
                style={{ backgroundColor: 'var(--brand-jade-700)' }}
            />

            <main
                id="main-content"
                className="mx-auto w-full max-w-2xl space-y-8 p-6"
            >
                <div className="flex items-center justify-between gap-4">
                    <AppLogoIcon className="size-9 fill-current text-primary" />
                    <LanguageToggle className="min-h-9 border bg-transparent py-1 text-foreground hover:bg-muted" />
                </div>

                <div className="space-y-2">
                    <h1 className="text-2xl font-bold">{t('invite.title')}</h1>
                    <p className="text-sm text-muted-foreground">
                        {t('invite.subtitle')}
                    </p>
                </div>

                <Form
                    action={`/invite/${token}`}
                    method="post"
                    options={{ preserveScroll: true }}
                    className="space-y-8"
                >
                    {({ processing, errors }) => (
                        <>
                            <section className="space-y-4 rounded-xl border p-4 sm:p-6">
                                <h2 className="text-sm font-medium">
                                    {t('invite.your_account')}
                                </h2>

                                <div className="grid gap-2">
                                    <Label>{t('auth.email')}</Label>
                                    {/* Fixed to the invitation: a forwarded
                                        link must not become somebody else's
                                        account. */}
                                    <Input
                                        value={email}
                                        readOnly
                                        dir="ltr"
                                        aria-describedby="email-note"
                                        className="bg-muted/50"
                                    />
                                    <p
                                        id="email-note"
                                        className="text-xs text-muted-foreground"
                                    >
                                        {t('invite.email_fixed')}
                                    </p>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="name">
                                        {t('invite.your_name')}
                                    </Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        required
                                        autoFocus
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="password">
                                            {t('auth.password')}
                                        </Label>
                                        <PasswordInput
                                            id="password"
                                            name="password"
                                            required
                                            autoComplete="new-password"
                                        />
                                        <InputError message={errors.password} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="password_confirmation">
                                            {t('invite.confirm_password')}
                                        </Label>
                                        <PasswordInput
                                            id="password_confirmation"
                                            name="password_confirmation"
                                            required
                                            autoComplete="new-password"
                                        />
                                    </div>
                                </div>
                            </section>

                            <section className="space-y-4 rounded-xl border p-4 sm:p-6">
                                <h2 className="flex items-center gap-2 text-sm font-medium">
                                    <Store className="size-4" aria-hidden />
                                    {t('invite.your_venue')}
                                </h2>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="place_name_ar">
                                            {t('owner.name_ar')}
                                        </Label>
                                        <Input
                                            id="place_name_ar"
                                            name="place_name_ar"
                                            dir="rtl"
                                            required
                                        />
                                        <InputError
                                            message={errors.place_name_ar}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="place_name_en">
                                            {t('owner.name_en')}
                                        </Label>
                                        <Input
                                            id="place_name_en"
                                            name="place_name_en"
                                            dir="ltr"
                                            required
                                        />
                                        <InputError
                                            message={errors.place_name_en}
                                        />
                                    </div>
                                    <div className="grid gap-2 sm:col-span-2">
                                        <Label htmlFor="whatsapp_number">
                                            {t('owner.whatsapp')}
                                        </Label>
                                        <Input
                                            id="whatsapp_number"
                                            name="whatsapp_number"
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
                                        {t('invite.first_location')}
                                    </h2>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {t('invite.first_location_hint')}
                                    </p>
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="location_name_ar">
                                            {t('owner.name_ar')}
                                        </Label>
                                        <Input
                                            id="location_name_ar"
                                            name="location_name_ar"
                                            dir="rtl"
                                            required
                                        />
                                        <InputError
                                            message={errors.location_name_ar}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="location_name_en">
                                            {t('owner.name_en')}
                                        </Label>
                                        <Input
                                            id="location_name_en"
                                            name="location_name_en"
                                            dir="ltr"
                                            required
                                        />
                                        <InputError
                                            message={errors.location_name_en}
                                        />
                                    </div>
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
                                    <div className="grid gap-2">
                                        <Label htmlFor="address_ar">
                                            {t('place.address_ar')}
                                        </Label>
                                        <Input
                                            id="address_ar"
                                            name="address_ar"
                                            dir="rtl"
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="address_en">
                                            {t('place.address_en')}
                                        </Label>
                                        <Input
                                            id="address_en"
                                            name="address_en"
                                            dir="ltr"
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="landmark_ar">
                                            {t('place.landmark_ar')}
                                        </Label>
                                        <Input
                                            id="landmark_ar"
                                            name="landmark_ar"
                                            dir="rtl"
                                            placeholder={t(
                                                'place.landmark_placeholder',
                                            )}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="landmark_en">
                                            {t('place.landmark_en')}
                                        </Label>
                                        <Input
                                            id="landmark_en"
                                            name="landmark_en"
                                            dir="ltr"
                                        />
                                    </div>
                                </div>
                            </section>

                            <Button
                                type="submit"
                                disabled={processing}
                                className="w-full cursor-pointer"
                            >
                                {processing ? <Spinner /> : <Check />}
                                {t('invite.finish')}
                            </Button>
                        </>
                    )}
                </Form>
            </main>
        </div>
    );
}
