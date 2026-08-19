import { MessageCircle } from 'lucide-react';
import { cn } from '@/lib/utils';

type Props = {
    number: string | null;
    message: string;
    label: string;
    className?: string;
};

/**
 * Deep link into WhatsApp. Renders nothing when the venue has not published a
 * number, rather than showing a dead button.
 */
export function WhatsAppButton({ number, message, label, className }: Props) {
    if (!number) {
        return null;
    }

    const href = `https://wa.me/${number.replace(/\D/g, '')}?text=${encodeURIComponent(message)}`;

    return (
        <a
            href={href}
            target="_blank"
            rel="noopener noreferrer"
            className={cn(
                'flex items-center justify-center gap-2 rounded-xl bg-[#25D366] py-3 font-medium text-white shadow-lg transition hover:brightness-95',
                className,
            )}
        >
            <MessageCircle className="size-5" />
            {label}
        </a>
    );
}
