import { Head } from '@inertiajs/react';
import { Check, Copy, Download, Sparkles, Upload } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useClipboard } from '@/hooks/use-clipboard';
import { localised, useLocale } from '@/lib/locale';
import { drawPoster } from '@/lib/poster-canvas';
import { useTranslation } from '@/lib/translation';
import { cn } from '@/lib/utils';

type Format = { key: string; width: number; height: number; ratio: string };
type Palette = { key: string; colors: string[] };

type Props = {
    event: {
        id: number;
        title_ar: string;
        title_en: string;
        starts_at: string;
        place_ar: string;
        place_en: string;
        qr_url: string;
    };
    formats: Format[];
    kinds: string[];
    moods: string[];
    palettes: Palette[];
};

/** A row of choices, rendered as real buttons rather than a select. */
function Choice({
    label,
    options,
    value,
    onChange,
    render,
}: {
    label: string;
    options: string[];
    value: string;
    onChange: (next: string) => void;
    render: (option: string) => React.ReactNode;
}) {
    return (
        <div className="space-y-2">
            <Label>{label}</Label>
            <div className="flex flex-wrap gap-2">
                {options.map((option) => (
                    <button
                        key={option}
                        type="button"
                        aria-pressed={value === option}
                        onClick={() => onChange(option)}
                        className={cn(
                            'min-h-11 cursor-pointer rounded-lg border px-3 py-2 text-sm transition-colors',
                            value === option
                                ? 'border-primary bg-primary/10 font-medium text-primary'
                                : 'hover:bg-muted/60',
                        )}
                    >
                        {render(option)}
                    </button>
                ))}
            </div>
        </div>
    );
}

export default function PosterWorkshop({
    event,
    formats,
    kinds,
    moods,
    palettes,
}: Props) {
    const t = useTranslation();
    const { locale, direction } = useLocale();
    const [copied, copy] = useClipboard();

    const [kind, setKind] = useState(kinds[0]);
    const [mood, setMood] = useState(moods[0]);
    const [palette, setPalette] = useState(palettes[0].key);
    const [format, setFormat] = useState(formats[0].key);

    const [prompt, setPrompt] = useState('');
    const [negative, setNegative] = useState('');
    const [size, setSize] = useState(formats[0]);

    const canvas = useRef<HTMLCanvasElement>(null);
    const [artwork, setArtwork] = useState<string | null>(null);
    const [drawError, setDrawError] = useState<string | null>(null);

    // Rebuilt server-side on every change: the prompt is written in the
    // owner's language, and the wording lives in the translation catalogue
    // rather than being assembled from fragments in the browser.
    useEffect(() => {
        const controller = new AbortController();

        fetch(`/owner/events/${event.id}/poster/prompt`, {
            method: 'POST',
            signal: controller.signal,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN':
                    document.querySelector<HTMLMetaElement>(
                        'meta[name="csrf-token"]',
                    )?.content ?? '',
            },
            body: JSON.stringify({ kind, mood, palette, format, locale }),
        })
            .then((response) => (response.ok ? response.json() : null))
            .then((data) => {
                if (!data) {
                    return;
                }

                setPrompt(data.prompt);
                setNegative(data.negative);
                setSize({
                    key: format,
                    width: data.width,
                    height: data.height,
                    ratio: data.ratio,
                });
            })
            .catch(() => {
                // An aborted request is the expected case while typing.
            });

        return () => controller.abort();
    }, [event.id, kind, mood, palette, format, locale]);

    // Redraw whenever the artwork or the target size changes.
    useEffect(() => {
        if (!artwork || !canvas.current) {
            return;
        }

        setDrawError(null);

        drawPoster(canvas.current, artwork, {
            width: size.width,
            height: size.height,
            title: localised(locale, event.title_ar, event.title_en),
            meta: `${new Date(event.starts_at).toLocaleDateString(
                locale === 'ar' ? 'ar-SY' : 'en-GB',
                { day: 'numeric', month: 'long' },
            )}  ·  ${localised(locale, event.place_ar, event.place_en)}`,
            rtl: direction === 'rtl',
            qrUrl: event.qr_url,
        }).catch(() => setDrawError(t('poster.draw_failed')));
    }, [artwork, size, locale, direction, event, t]);

    return (
        <>
            <Head title={t('poster.title')} />

            <div className="space-y-6 p-4">
                <Heading
                    variant="small"
                    title={t('poster.title')}
                    description={t('poster.subtitle')}
                />

                <ol className="grid gap-4 lg:grid-cols-2">
                    <li className="space-y-5 rounded-xl border p-4 sm:p-6">
                        <h2 className="flex items-center gap-2 font-semibold">
                            <Sparkles
                                className="size-4 text-primary"
                                aria-hidden
                            />
                            {t('poster.step_one')}
                        </h2>

                        <Choice
                            label={t('poster.kind_label')}
                            options={kinds}
                            value={kind}
                            onChange={setKind}
                            render={(k) => t(`poster.kind_${k}`)}
                        />
                        <Choice
                            label={t('poster.mood_label')}
                            options={moods}
                            value={mood}
                            onChange={setMood}
                            render={(m) => t(`poster.mood_${m}`)}
                        />
                        <Choice
                            label={t('poster.palette_label')}
                            options={palettes.map((p) => p.key)}
                            value={palette}
                            onChange={setPalette}
                            render={(key) => (
                                <span className="flex items-center gap-2">
                                    <span className="flex" aria-hidden>
                                        {palettes
                                            .find((p) => p.key === key)
                                            ?.colors.map((c) => (
                                                <span
                                                    key={c}
                                                    className="-ms-1 size-3.5 rounded-full ring-1 ring-black/10 first:ms-0"
                                                    style={{
                                                        backgroundColor: c,
                                                    }}
                                                />
                                            ))}
                                    </span>
                                    {t(`poster.palette_${key}`)}
                                </span>
                            )}
                        />
                        <Choice
                            label={t('poster.format_label')}
                            options={formats.map((f) => f.key)}
                            value={format}
                            onChange={setFormat}
                            render={(key) => {
                                const f = formats.find((x) => x.key === key);

                                return (
                                    <span className="flex flex-col items-start">
                                        {t(`poster.format_${key}`)}
                                        <span className="text-xs text-muted-foreground tabular-nums">
                                            {f?.width}×{f?.height}
                                        </span>
                                    </span>
                                );
                            }}
                        />

                        <div className="space-y-2">
                            <Label htmlFor="prompt">
                                {t('poster.prompt_label')}
                            </Label>
                            <textarea
                                id="prompt"
                                readOnly
                                value={prompt}
                                rows={9}
                                dir="auto"
                                onFocus={(e) => e.currentTarget.select()}
                                className="w-full rounded-lg border bg-muted/40 p-3 text-sm leading-relaxed"
                            />
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    onClick={() => copy(prompt)}
                                    className="cursor-pointer"
                                >
                                    {copied === prompt ? <Check /> : <Copy />}
                                    {copied === prompt
                                        ? t('share.copied')
                                        : t('poster.copy_prompt')}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => copy(negative)}
                                    className="cursor-pointer"
                                >
                                    {copied === negative ? <Check /> : <Copy />}
                                    {t('poster.copy_negative')}
                                </Button>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                {t('poster.why_no_text')}
                            </p>
                        </div>
                    </li>

                    <li className="space-y-4 rounded-xl border p-4 sm:p-6">
                        <h2 className="flex items-center gap-2 font-semibold">
                            <Upload
                                className="size-4 text-primary"
                                aria-hidden
                            />
                            {t('poster.step_two')}
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            {t('poster.step_two_hint')}
                        </p>

                        <Label htmlFor="artwork">
                            {t('poster.artwork_label')}
                        </Label>
                        <input
                            id="artwork"
                            type="file"
                            accept="image/*"
                            className="block w-full cursor-pointer rounded-lg border p-2 text-sm file:me-3 file:cursor-pointer file:rounded-md file:border-0 file:bg-primary file:px-3 file:py-2 file:text-primary-foreground"
                            onChange={(e) => {
                                const file = e.target.files?.[0];

                                if (file) {
                                    // A local object URL: the artwork never
                                    // leaves the owner's machine.
                                    setArtwork(URL.createObjectURL(file));
                                }
                            }}
                        />

                        {drawError && (
                            <p className="text-sm text-destructive">
                                {drawError}
                            </p>
                        )}

                        <div className="overflow-hidden rounded-xl border bg-muted/40">
                            <canvas
                                ref={canvas}
                                className="block h-auto w-full"
                                aria-label={t('poster.preview_label')}
                            />
                        </div>

                        <Button
                            type="button"
                            disabled={!artwork}
                            className="w-full cursor-pointer"
                            onClick={() => {
                                canvas.current?.toBlob((blob) => {
                                    if (!blob) {
                                        return;
                                    }

                                    const url = URL.createObjectURL(blob);
                                    const link = document.createElement('a');
                                    link.href = url;
                                    link.download = `poster-${format}.png`;
                                    link.click();
                                    URL.revokeObjectURL(url);
                                }, 'image/png');
                            }}
                        >
                            <Download />
                            {t('poster.download')}
                        </Button>
                    </li>
                </ol>
            </div>
        </>
    );
}

PosterWorkshop.layout = {
    breadcrumbs: [{ title: 'poster.title', href: '' }],
};
