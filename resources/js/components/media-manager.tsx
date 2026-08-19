import { Form, router } from '@inertiajs/react';
import { Image as ImageIcon, Star, Trash2, Video } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/lib/translation';

export type MediaItem = {
    id: number;
    type: 'image' | 'video';
    path: string;
    poster_path: string | null;
    is_promo: boolean;
};

/**
 * Photos and promo videos for an event.
 *
 * Uploads post immediately rather than waiting for the parent form: a 100 MB
 * video should not be re-sent every time the owner corrects a typo elsewhere
 * on the page.
 */
export function MediaManager({
    eventId,
    media,
}: {
    eventId: number;
    media: MediaItem[];
}) {
    const t = useTranslation();

    return (
        <section className="space-y-4">
            <div>
                <h3 className="text-sm font-medium">{t('form.media')}</h3>
                <p className="text-xs text-muted-foreground">
                    {t('form.media_hint')}
                </p>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                {(['image', 'video'] as const).map((kind) => (
                    <Form
                        key={kind}
                        action={`/owner/events/${eventId}/media`}
                        method="post"
                        encType="multipart/form-data"
                        options={{ preserveScroll: true }}
                        className="grid gap-2"
                    >
                        {({ processing, errors }) => (
                            <>
                                <input type="hidden" name="kind" value={kind} />
                                <Label htmlFor={`media-${kind}`}>
                                    {kind === 'video'
                                        ? t('form.add_video')
                                        : t('form.add_photo')}
                                </Label>
                                <Input
                                    id={`media-${kind}`}
                                    name="file"
                                    type="file"
                                    accept={
                                        kind === 'video' ? 'video/*' : 'image/*'
                                    }
                                    disabled={processing}
                                    onChange={(e) => {
                                        if (e.target.files?.length) {
                                            e.currentTarget.form?.requestSubmit();
                                        }
                                    }}
                                />
                                {errors.file && (
                                    <p className="text-xs text-destructive">
                                        {errors.file}
                                    </p>
                                )}
                            </>
                        )}
                    </Form>
                ))}
            </div>

            {media.length > 0 && (
                <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    {media.map((item) => (
                        <li
                            key={item.id}
                            className="group relative overflow-hidden rounded-xl border"
                        >
                            <div className="aspect-video bg-muted">
                                {item.type === 'image' ? (
                                    <img
                                        src={`/storage/${item.path}`}
                                        alt=""
                                        className="size-full object-cover"
                                        loading="lazy"
                                    />
                                ) : item.poster_path ? (
                                    <img
                                        src={`/storage/${item.poster_path}`}
                                        alt=""
                                        className="size-full object-cover"
                                        loading="lazy"
                                    />
                                ) : (
                                    <span className="flex size-full items-center justify-center text-muted-foreground">
                                        <Video className="size-6" />
                                    </span>
                                )}
                            </div>

                            <div className="flex items-center justify-between gap-1 p-2">
                                <span className="inline-flex items-center gap-1 text-xs text-muted-foreground">
                                    {item.type === 'video' ? (
                                        <Video className="size-3.5" />
                                    ) : (
                                        <ImageIcon className="size-3.5" />
                                    )}
                                </span>

                                <span className="flex items-center gap-1">
                                    {item.type === 'video' && (
                                        <Button
                                            type="button"
                                            size="icon"
                                            variant={
                                                item.is_promo
                                                    ? 'default'
                                                    : 'ghost'
                                            }
                                            aria-label={
                                                item.is_promo
                                                    ? t('form.promo_active')
                                                    : t('form.set_promo')
                                            }
                                            className="cursor-pointer"
                                            onClick={() =>
                                                router.post(
                                                    `/owner/events/${eventId}/media/${item.id}/promo`,
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <Star
                                                className="size-4"
                                                fill={
                                                    item.is_promo
                                                        ? 'currentColor'
                                                        : 'none'
                                                }
                                            />
                                        </Button>
                                    )}

                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="ghost"
                                        aria-label={t('form.remove')}
                                        className="cursor-pointer text-destructive"
                                        onClick={() =>
                                            router.delete(
                                                `/owner/events/${eventId}/media/${item.id}`,
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                </span>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
