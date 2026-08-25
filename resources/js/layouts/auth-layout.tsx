import { FlashToaster } from '@/components/flash-toaster';
import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';
import { useTranslation } from '@/lib/translation';

export default function AuthLayout({
    title = '',
    description = '',
    children,
}: {
    title?: string;
    description?: string;
    children: React.ReactNode;
}) {
    // Auth titles are declared in static page config, outside any component,
    // so they carry catalogue keys resolved here. An unknown key falls back to
    // the literal string.
    const t = useTranslation();

    return (
        <AuthLayoutTemplate title={t(title)} description={t(description)}>
            <FlashToaster />
            {children}
        </AuthLayoutTemplate>
    );
}
