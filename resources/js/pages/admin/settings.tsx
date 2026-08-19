import { Form, Head } from '@inertiajs/react';
import { Save } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/lib/translation';
import { settings } from '@/routes/admin';

type Props = {
    settings: {
        app_name_en: string;
        app_name_ar: string;
        tagline_en: string | null;
        tagline_ar: string | null;
        logo_path: string | null;
        support_whatsapp: string | null;
    };
};

export default function PlatformSettings({ settings: values }: Props) {
    const t = useTranslation();

    return (
        <>
            <Head title={t('admin.settings')} />

            <div className="max-w-2xl space-y-6 p-4">
                <Heading
                    variant="small"
                    title={t('admin.settings')}
                    description={t('admin.settings_hint')}
                />

                {values.logo_path && (
                    <img
                        src={`/storage/${values.logo_path}`}
                        alt={t('admin.logo')}
                        className="h-14 w-auto rounded-lg bg-muted p-2"
                    />
                )}

                <Form
                    action="/admin/settings"
                    method="post"
                    encType="multipart/form-data"
                    options={{ preserveScroll: true }}
                    className="grid gap-4 sm:grid-cols-2"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="app_name_en">
                                    {t('admin.app_name_en')}
                                </Label>
                                <Input
                                    id="app_name_en"
                                    name="app_name_en"
                                    dir="ltr"
                                    defaultValue={values.app_name_en}
                                    required
                                />
                                <InputError message={errors.app_name_en} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="app_name_ar">
                                    {t('admin.app_name_ar')}
                                </Label>
                                <Input
                                    id="app_name_ar"
                                    name="app_name_ar"
                                    dir="rtl"
                                    defaultValue={values.app_name_ar}
                                    required
                                />
                                <InputError message={errors.app_name_ar} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="tagline_en">
                                    {t('admin.tagline_en')}
                                </Label>
                                <Input
                                    id="tagline_en"
                                    name="tagline_en"
                                    dir="ltr"
                                    defaultValue={values.tagline_en ?? ''}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="tagline_ar">
                                    {t('admin.tagline_ar')}
                                </Label>
                                <Input
                                    id="tagline_ar"
                                    name="tagline_ar"
                                    dir="rtl"
                                    defaultValue={values.tagline_ar ?? ''}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="support_whatsapp">
                                    {t('admin.support_whatsapp')}
                                </Label>
                                <Input
                                    id="support_whatsapp"
                                    name="support_whatsapp"
                                    type="tel"
                                    dir="ltr"
                                    placeholder="09XXXXXXXX"
                                    defaultValue={values.support_whatsapp ?? ''}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="logo">{t('admin.logo')}</Label>
                                <Input
                                    id="logo"
                                    name="logo"
                                    type="file"
                                    accept="image/png,image/jpeg,image/webp"
                                />
                                <p className="text-xs text-muted-foreground">
                                    {t('admin.logo_hint')}
                                </p>
                                <InputError message={errors.logo} />
                            </div>

                            <Button
                                type="submit"
                                disabled={processing}
                                className="cursor-pointer sm:col-span-2"
                            >
                                <Save />
                                {t('admin.save')}
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

PlatformSettings.layout = {
    breadcrumbs: [{ title: 'admin.settings', href: settings() }],
};
