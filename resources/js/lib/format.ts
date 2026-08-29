/**
 * One rule for every figure the app prints.
 *
 * Arabic has two numeral scripts and the app was using both: dates rendered
 * Arabic-Indic through `ar-SY`, while prices, seat counts, phone numbers and
 * reference codes rendered Latin. A single line could carry both -- "14 من 89
 * مقعد · ١٣ أيلول" -- which reads as a bug to anyone who can read it.
 *
 * The rule is Latin digits throughout. Not because Arabic-Indic is wrong, but
 * because half the figures here cannot be anything else: a currency amount
 * sits beside a Latin currency code, a phone number is dialled as typed, and a
 * booking reference is read out character by character. Month and day *names*
 * stay Arabic -- only the numerals are pinned, via the `-u-nu-latn` extension.
 */

type Locale = 'ar' | 'en';

/**
 * The BCP-47 tag to format dates with. Arabic keeps its month names and loses
 * its numeral script.
 */
export function dateTag(locale: Locale): string {
    return locale === 'ar' ? 'ar-SY-u-nu-latn' : 'en-GB';
}

export function formatDate(
    value: string | number | Date,
    locale: Locale,
    options: Intl.DateTimeFormatOptions = { dateStyle: 'medium' },
): string {
    return new Date(value).toLocaleDateString(dateTag(locale), options);
}

export function formatTime(
    value: string | number | Date,
    locale: Locale,
    options: Intl.DateTimeFormatOptions = { timeStyle: 'short' },
): string {
    return new Date(value).toLocaleTimeString(dateTag(locale), options);
}

export function formatDateTime(
    value: string | number | Date,
    locale: Locale,
    options: Intl.DateTimeFormatOptions = {
        dateStyle: 'medium',
        timeStyle: 'short',
    },
): string {
    return new Date(value).toLocaleString(dateTag(locale), options);
}

/**
 * Group separators for a plain figure.
 *
 * Pinned rather than left to the browser: a bare `toLocaleString()` follows
 * the *device* locale, so the same page renders Arabic-Indic digits on an
 * Arabic phone and Latin ones on the laptop it was built on.
 */
export function formatNumber(
    value: number,
    options: Intl.NumberFormatOptions = {},
): string {
    return value.toLocaleString('en-GB', options);
}

/**
 * A price with its currency code, in the order the direction expects.
 */
export function formatMoney(value: number, currency: string): string {
    return `${formatNumber(value)} ${currency}`;
}
