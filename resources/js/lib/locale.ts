import { usePage } from '@inertiajs/react';

export type SharedLocale = { locale: 'ar' | 'en'; direction: 'rtl' | 'ltr' };

/**
 * Current locale and text direction, shared from the server on every page.
 */
export function useLocale(): SharedLocale {
    const { locale, direction } = usePage<SharedLocale>().props;

    return { locale: locale ?? 'ar', direction: direction ?? 'rtl' };
}

/**
 * Pick the Arabic or English variant of a bilingual field.
 */
export function localised(
    locale: 'ar' | 'en',
    ar: string | null | undefined,
    en: string | null | undefined,
): string {
    return (locale === 'ar' ? ar : en) ?? en ?? ar ?? '';
}
