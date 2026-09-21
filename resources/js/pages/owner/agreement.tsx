import { Form, Head, Link, usePage } from '@inertiajs/react';
import { CheckCircle2, LogOut, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { Wordmark } from '@/components/brand/wordmark';
import { FlashToaster } from '@/components/flash-toaster';
import InputError from '@/components/input-error';
import { LanguageToggle } from '@/components/language-toggle';
import { ThemeToggle } from '@/components/theme-toggle';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { dateTag } from '@/lib/format';
import { localised, useLocale } from '@/lib/locale';
import { useTranslation } from '@/lib/translation';
import { dashboard, home, logout } from '@/routes';

type Agreement = {
    id: number;
    version: string;
    title_ar: string;
    title_en: string;
    body_ar: string;
    body_en: string;
    change_note_ar: string | null;
    change_note_en: string | null;
    published_at: string | null;
};

type Accepted = {
    accepted_at: string;
    legal_name: string;
    representative_name: string;
    representative_title: string | null;
    representative_phone: string;
    otp_channel: string;
    content_hash: string;
};

type Place = {
    name_ar: string;
    name_en: string;
    legal_name: string | null;
    registration_number: string | null;
    representative_name: string | null;
    representative_title: string | null;
    representative_phone: string | null;
};

type Props = {
    agreement: Agreement | null;
    accepted: Accepted | null;
    previous: { version: string; accepted_at: string } | null;
    place: Place | null;
    otp: { enabled: boolean; sent_to: string | null };
};

/**
 * The Partner Terms, as the venue meets them.
 *
 * A bare page in front of the app rather than a screen inside it: while a
 * version is unaccepted every venue route redirects here, so a sidebar
 * would be a row of links that bounce straight back. Once accepted the same
 * page is the record of what was signed.
 *
 * The checkbox starts unticked and is a real form control: the server
 * requires it to be present and true, so there is no way to submit without
 * an explicit act.
 */
export default function OwnerAgreement({
    agreement,
    accepted,
    previous,
    place,
    otp,
}: Props) {
    const t = useTranslation();
    const { locale } = useLocale();
    const { auth } = usePage<{ auth: { user: { name: string } } }>().props;
    const dateLocale = dateTag(locale);
    const [phone, setPhone] = useState(place?.representative_phone ?? '');

    const placeName = place
        ? localised(locale, place.name_ar, place.name_en)
        : '';
    const formatDate = (iso: string) =>
        new Date(iso).toLocaleDateString(dateLocale, { dateStyle: 'long' });

    return (
        <div className="flex min-h-svh flex-col bg-background">
            <Head title={t('agreement.title')} />
            <FlashToaster />

            <div className="h-1.5 w-full shrink-0 bg-primary" />

            <main
                id="main-content"
                className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-8 p-6 md:p-10"
            >
                <header className="flex items-center justify-between gap-4">
                    <Link
                        href={home()}
                        className="flex items-center rounded-md coarse:min-h-11 coarse:min-w-11"
                    >
                        <Wordmark decorative className="h-9 text-foreground" />
                        <span className="sr-only">{t('common.back_home')}</span>
                    </Link>

                    <div className="flex items-center gap-2">
                        <ThemeToggle className="border bg-transparent text-foreground hover:bg-muted" />
                        <LanguageToggle className="border bg-transparent text-foreground hover:bg-muted" />
                    </div>
                </header>

                {agreement === null ? (
                    <section className="space-y-2 rounded-xl border p-6">
                        <h1 className="text-2xl font-extrabold">
                            {t('agreement.none_title')}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {t('agreement.none_body')}
                        </p>
                        <Button asChild variant="outline" className="mt-2">
                            <Link href={dashboard()}>
                                {t('agreement.back_to_dashboard')}
                            </Link>
                        </Button>
                    </section>
                ) : (
                    <>
                        <div className="space-y-2">
                            <p className="text-xs font-extrabold tracking-wide text-primary-text uppercase">
                                {accepted
                                    ? t('agreement.accepted_title')
                                    : previous
                                      ? t('agreement.updated_title')
                                      : t('agreement.gate_title')}
                            </p>
                            <h1 className="text-3xl font-extrabold">
                                {localised(
                                    locale,
                                    agreement.title_ar,
                                    agreement.title_en,
                                )}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                {t('agreement.version', {
                                    version: agreement.version,
                                })}
                                {agreement.published_at && (
                                    <>
                                        {' · '}
                                        {t('agreement.in_force_since', {
                                            date: formatDate(
                                                agreement.published_at,
                                            ),
                                        })}
                                    </>
                                )}
                            </p>

                            {accepted ? (
                                <p className="max-w-[65ch] text-sm text-muted-foreground">
                                    {t('agreement.accepted_body', {
                                        version: agreement.version,
                                    })}
                                </p>
                            ) : previous ? (
                                <p className="max-w-[65ch] text-sm text-muted-foreground">
                                    {t('agreement.updated_intro', {
                                        previous: previous.version,
                                        date: formatDate(previous.accepted_at),
                                        version: agreement.version,
                                    })}
                                </p>
                            ) : (
                                <p className="max-w-[65ch] text-sm text-muted-foreground">
                                    {t('agreement.gate_intro', {
                                        place: placeName,
                                    })}
                                </p>
                            )}
                        </div>

                        {previous &&
                            !accepted &&
                            localised(
                                locale,
                                agreement.change_note_ar,
                                agreement.change_note_en,
                            ) && (
                                <section className="space-y-1 rounded-xl border border-primary/40 bg-primary/5 p-4">
                                    <h2 className="text-sm font-extrabold">
                                        {t('agreement.change_note')}
                                    </h2>
                                    <p className="text-sm whitespace-pre-line">
                                        {localised(
                                            locale,
                                            agreement.change_note_ar,
                                            agreement.change_note_en,
                                        )}
                                    </p>
                                </section>
                            )}

                        {/* The text itself, in the reader's language. The
                            header switch changes language for the whole
                            page, so what is accepted is always readable. */}
                        <article className="max-h-[55vh] overflow-y-auto rounded-xl border bg-card p-5 text-sm leading-relaxed whitespace-pre-line sm:p-6">
                            {localised(
                                locale,
                                agreement.body_ar,
                                agreement.body_en,
                            )}
                        </article>

                        {accepted ? (
                            <AcceptedRecord
                                accepted={accepted}
                                formatDate={formatDate}
                                signer={auth.user.name}
                            />
                        ) : (
                            place && (
                                <AcceptForm
                                    agreement={agreement}
                                    place={place}
                                    placeName={placeName}
                                    otp={otp}
                                    phone={phone}
                                    onPhoneChange={setPhone}
                                />
                            )
                        )}
                    </>
                )}
            </main>
        </div>
    );
}

function AcceptedRecord({
    accepted,
    formatDate,
    signer,
}: {
    accepted: Accepted;
    formatDate: (iso: string) => string;
    signer: string;
}) {
    const t = useTranslation();

    return (
        <section className="space-y-4 rounded-xl border p-5 sm:p-6">
            <h2 className="flex items-center gap-2 text-lg font-extrabold">
                <CheckCircle2
                    className="size-5 text-status-paid-fg"
                    aria-hidden="true"
                />
                {t('agreement.record')}
            </h2>

            <dl className="grid gap-3 text-sm sm:grid-cols-2">
                <Row
                    label={t('agreement.accepted_title')}
                    value={t('agreement.accepted_on', {
                        date: formatDate(accepted.accepted_at),
                        name: signer,
                    })}
                />
                <Row
                    label={t('agreement.legal_name')}
                    value={accepted.legal_name}
                />
                <Row
                    label={t('agreement.representative_name')}
                    value={
                        accepted.representative_title
                            ? `${accepted.representative_name} · ${accepted.representative_title}`
                            : accepted.representative_name
                    }
                />
                <Row
                    label={t('agreement.representative_phone')}
                    value={accepted.representative_phone}
                    ltr
                />
                <Row
                    label={t('agreement.code_title')}
                    value={
                        accepted.otp_channel === 'none'
                            ? t('agreement.otp_none')
                            : t('agreement.otp_via', {
                                  channel: accepted.otp_channel,
                              })
                    }
                />
                <Row
                    label={t('agreement.hash')}
                    value={accepted.content_hash.slice(0, 16)}
                    mono
                />
            </dl>

            <Button asChild>
                <Link href={dashboard()}>
                    {t('agreement.back_to_dashboard')}
                </Link>
            </Button>
        </section>
    );
}

function Row({
    label,
    value,
    ltr,
    mono,
}: {
    label: string;
    value: string;
    ltr?: boolean;
    mono?: boolean;
}) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd
                dir={ltr ? 'ltr' : undefined}
                className={
                    mono
                        ? 'font-mono text-xs'
                        : 'font-medium ' + (ltr ? 'text-start' : '')
                }
            >
                {value}
            </dd>
        </div>
    );
}

function AcceptForm({
    agreement,
    place,
    placeName,
    otp,
    phone,
    onPhoneChange,
}: {
    agreement: Agreement;
    place: Place;
    placeName: string;
    otp: Props['otp'];
    phone: string;
    onPhoneChange: (value: string) => void;
}) {
    const t = useTranslation();
    const [ticked, setTicked] = useState(false);

    return (
        <>
            {/* Sending the code is its own small form, because forms cannot
                nest and the code must go to the number as typed. */}
            {otp.enabled && (
                <section className="space-y-3 rounded-xl border p-5 sm:p-6">
                    <h2 className="text-lg font-extrabold">
                        {t('agreement.code_title')}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {t('agreement.code_hint')}
                    </p>
                    <Form
                        action="/owner/agreement/code"
                        method="post"
                        options={{ preserveScroll: true }}
                        className="flex flex-wrap items-end gap-3"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid min-w-0 flex-1 gap-2">
                                    <Label htmlFor="otp_phone">
                                        {t('agreement.representative_phone')}
                                    </Label>
                                    <Input
                                        id="otp_phone"
                                        name="representative_phone"
                                        type="tel"
                                        inputMode="tel"
                                        dir="ltr"
                                        required
                                        value={phone}
                                        onChange={(e) =>
                                            onPhoneChange(e.target.value)
                                        }
                                    />
                                    <InputError
                                        message={errors.representative_phone}
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    variant="outline"
                                    disabled={processing}
                                >
                                    {processing ? <Spinner /> : null}
                                    {t('agreement.send_code')}
                                </Button>
                            </>
                        )}
                    </Form>
                </section>
            )}

            <Form
                action="/owner/agreement"
                method="post"
                options={{ preserveScroll: true }}
                className="space-y-6"
            >
                {({ processing, errors }) => (
                    <>
                        <input
                            type="hidden"
                            name="agreement_version_id"
                            value={agreement.id}
                        />
                        <InputError message={errors.agreement_version_id} />

                        <section className="space-y-4 rounded-xl border p-5 sm:p-6">
                            <div>
                                <h2 className="text-lg font-extrabold">
                                    {t('agreement.identity')}
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    {t('agreement.identity_hint')}
                                </p>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    id="legal_name"
                                    label={t('agreement.legal_name')}
                                    error={errors.legal_name}
                                    required
                                >
                                    <Input
                                        id="legal_name"
                                        name="legal_name"
                                        required
                                        defaultValue={place.legal_name ?? ''}
                                    />
                                </Field>

                                <Field
                                    id="registration_number"
                                    label={t('agreement.registration_number')}
                                    error={errors.registration_number}
                                >
                                    <Input
                                        id="registration_number"
                                        name="registration_number"
                                        dir="ltr"
                                        defaultValue={
                                            place.registration_number ?? ''
                                        }
                                    />
                                </Field>

                                <Field
                                    id="representative_name"
                                    label={t('agreement.representative_name')}
                                    error={errors.representative_name}
                                    required
                                >
                                    <Input
                                        id="representative_name"
                                        name="representative_name"
                                        required
                                        defaultValue={
                                            place.representative_name ?? ''
                                        }
                                    />
                                </Field>

                                <Field
                                    id="representative_title"
                                    label={t('agreement.representative_title')}
                                    error={errors.representative_title}
                                >
                                    <Input
                                        id="representative_title"
                                        name="representative_title"
                                        defaultValue={
                                            place.representative_title ?? ''
                                        }
                                    />
                                </Field>

                                <Field
                                    id="representative_phone"
                                    label={t('agreement.representative_phone')}
                                    error={errors.representative_phone}
                                    required
                                >
                                    <Input
                                        id="representative_phone"
                                        name="representative_phone"
                                        type="tel"
                                        inputMode="tel"
                                        dir="ltr"
                                        required
                                        placeholder="09XXXXXXXX"
                                        value={phone}
                                        onChange={(e) =>
                                            onPhoneChange(e.target.value)
                                        }
                                    />
                                </Field>

                                {otp.enabled && (
                                    <Field
                                        id="otp_code"
                                        label={t('agreement.code_label')}
                                        error={errors.otp_code}
                                        required
                                    >
                                        <Input
                                            id="otp_code"
                                            name="otp_code"
                                            inputMode="numeric"
                                            autoComplete="one-time-code"
                                            dir="ltr"
                                            maxLength={6}
                                            required
                                            className="font-mono tracking-[0.3em]"
                                        />
                                    </Field>
                                )}
                            </div>
                        </section>

                        <label
                            htmlFor="accept"
                            className="flex min-h-11 cursor-pointer items-start gap-3 rounded-xl border p-4 text-sm"
                        >
                            <Checkbox
                                id="accept"
                                name="accept"
                                value="1"
                                checked={ticked}
                                onCheckedChange={(value) =>
                                    setTicked(value === true)
                                }
                                className="mt-0.5 cursor-pointer"
                            />
                            <span>
                                {t('agreement.accept_label', {
                                    version: agreement.version,
                                    place: placeName,
                                })}
                            </span>
                        </label>
                        <InputError message={errors.accept} />

                        <div className="flex flex-wrap items-center gap-3">
                            <Button
                                type="submit"
                                size="lg"
                                disabled={processing || !ticked}
                            >
                                {processing ? <Spinner /> : <ShieldCheck />}
                                {t('agreement.accept_button')}
                            </Button>

                            {/* Somebody who will not sign has a way out that
                                is not "close the tab". */}
                            <Link
                                href={logout()}
                                as="button"
                                className="inline-flex min-h-11 cursor-pointer items-center gap-1.5 rounded-md px-3 text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                            >
                                <LogOut className="size-4" aria-hidden="true" />
                                {t('agreement.decline')}
                            </Link>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

function Field({
    id,
    label,
    error,
    children,
    required = false,
}: {
    id: string;
    label: string;
    error?: string;
    children: React.ReactNode;
    required?: boolean;
}) {
    const t = useTranslation();

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>
                {label}
                {required ? (
                    <>
                        <span aria-hidden="true" className="text-primary-text">
                            {' '}
                            *
                        </span>
                        <span className="sr-only">
                            {' '}
                            ({t('form.required_mark')})
                        </span>
                    </>
                ) : (
                    <span className="ms-1 text-xs font-normal text-muted-foreground">
                        ({t('agreement.optional')})
                    </span>
                )}
            </Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
