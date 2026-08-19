import { usePage } from '@inertiajs/react';

type TranslationProps = { translations: Record<string, string> };

export type Translator = (
    key: string,
    replacements?: Record<string, string | number>,
) => string;

/**
 * Translator backed by the server's `ui` language files, shared on every page.
 *
 * Returns the key itself when a string is missing, which makes gaps obvious in
 * the UI rather than rendering an empty element.
 */
export function useTranslation(): Translator {
    const { translations } = usePage<TranslationProps>().props;

    return (key, replacements) => {
        let value = translations?.[key] ?? key;

        for (const [token, replacement] of Object.entries(replacements ?? {})) {
            value = value.replace(`:${token}`, String(replacement));
        }

        return value;
    };
}
