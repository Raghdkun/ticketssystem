import { Check, Copy, Facebook, Link2, Send, Share2 } from 'lucide-react';
import { useEffect, useState, useSyncExternalStore } from 'react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useClipboard } from '@/hooks/use-clipboard';
import { useTranslation } from '@/lib/translation';
import { cn } from '@/lib/utils';

type Props = {
    url: string;
    title: string;
    text: string;
    className?: string;
    /** Icon-only on narrow screens where a labelled button would crowd the row. */
    compact?: boolean;
};

/** WhatsApp's own glyph, since lucide has no brand mark for it. */
function WhatsAppGlyph({ className }: { className?: string }) {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="currentColor"
            className={className}
            aria-hidden="true"
        >
            <path d="M17.47 14.38c-.3-.15-1.75-.86-2.02-.96-.27-.1-.47-.15-.67.15s-.77.96-.94 1.16-.35.22-.65.08a8.2 8.2 0 0 1-2.4-1.48 9 9 0 0 1-1.66-2.07c-.17-.3 0-.46.13-.6.14-.14.3-.35.45-.53.15-.18.2-.3.3-.5.1-.2.05-.38-.02-.53-.08-.15-.67-1.6-.92-2.2-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.37s-1.05 1.02-1.05 2.5 1.08 2.9 1.23 3.1c.15.2 2.12 3.24 5.14 4.54.72.31 1.28.5 1.71.63.72.23 1.38.2 1.9.12.58-.09 1.75-.72 2-1.41.25-.7.25-1.29.17-1.41-.07-.13-.27-.2-.57-.35Z" />
            <path d="M12.04 2A9.9 9.9 0 0 0 2.1 11.9c0 1.75.46 3.46 1.34 4.97L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.004A9.9 9.9 0 0 0 22 11.94 9.86 9.86 0 0 0 12.04 2Zm5.8 14.03a8.2 8.2 0 0 1-11.6 1.3l-.42-.25-3.11.82.83-3.04-.27-.44a8.22 8.22 0 1 1 14.57 1.61Z" />
        </svg>
    );
}

/**
 * Share an event.
 *
 * Prefers the platform's own share sheet where the browser has one — on a
 * phone that reaches every installed app, which beats any list we could hard
 * code. Where it does not exist (most desktops) it falls back to an explicit
 * menu of the channels these events actually travel on, with copy-link as the
 * universal option.
 */
export function ShareButton({ url, title, text, className, compact }: Props) {
    const t = useTranslation();
    const [copied, copy] = useClipboard();
    const [justCopied, setJustCopied] = useState(false);

    // navigator.share exists only on the client, and only over HTTPS or
    // localhost. Read as an external store rather than set from an effect:
    // the value never changes, which avoids both an extra render pass and a
    // server/client hydration mismatch.
    const canShareNatively = useSyncExternalStore(
        () => () => {},
        () => typeof navigator !== 'undefined' && !!navigator.share,
        () => false,
    );

    useEffect(() => {
        if (!justCopied) {
            return;
        }

        const timer = setTimeout(() => setJustCopied(false), 2000);

        return () => clearTimeout(timer);
    }, [justCopied]);

    const share = `${text}\n${url}`;
    const encoded = {
        url: encodeURIComponent(url),
        share: encodeURIComponent(share),
        title: encodeURIComponent(title),
    };

    const channels = [
        {
            key: 'whatsapp',
            href: `https://wa.me/?text=${encoded.share}`,
            icon: <WhatsAppGlyph className="size-4" />,
        },
        {
            key: 'telegram',
            href: `https://t.me/share/url?url=${encoded.url}&text=${encoded.title}`,
            icon: <Send className="size-4" />,
        },
        {
            key: 'x',
            href: `https://twitter.com/intent/tweet?url=${encoded.url}&text=${encoded.title}`,
            icon: <span className="text-[15px] leading-none font-bold">𝕏</span>,
        },
        {
            key: 'facebook',
            href: `https://www.facebook.com/sharer/sharer.php?u=${encoded.url}`,
            icon: <Facebook className="size-4" />,
        },
    ] as const;

    const openNativeSheet = async () => {
        try {
            await navigator.share({ title, text, url });
        } catch {
            // The user dismissed the sheet, which is not an error.
        }
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    size={compact ? 'icon' : 'default'}
                    aria-label={t('share.share')}
                    className={cn('cursor-pointer', className)}
                >
                    <Share2 />
                    {!compact && t('share.share')}
                </Button>
            </DropdownMenuTrigger>

            <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuLabel>{t('share.share_title')}</DropdownMenuLabel>
                <DropdownMenuSeparator />

                {channels.map((channel) => (
                    <DropdownMenuItem key={channel.key} asChild>
                        <a
                            href={channel.href}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="cursor-pointer"
                        >
                            <span className="flex size-4 items-center justify-center">
                                {channel.icon}
                            </span>
                            {t(`share.${channel.key}`)}
                        </a>
                    </DropdownMenuItem>
                ))}

                <DropdownMenuSeparator />

                <DropdownMenuItem
                    className="cursor-pointer"
                    onSelect={(event) => {
                        // Keep the menu open so the "copied" state is visible.
                        event.preventDefault();
                        void copy(url).then(() => setJustCopied(true));
                    }}
                >
                    {justCopied || copied === url ? (
                        <Check className="size-4 text-emerald-600 dark:text-emerald-400" />
                    ) : (
                        <Copy className="size-4" />
                    )}
                    {justCopied ? t('share.copied') : t('share.copy_link')}
                </DropdownMenuItem>

                {canShareNatively && (
                    <DropdownMenuItem
                        className="cursor-pointer"
                        onSelect={() => void openNativeSheet()}
                    >
                        <Link2 className="size-4" />
                        {t('share.native')}
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
