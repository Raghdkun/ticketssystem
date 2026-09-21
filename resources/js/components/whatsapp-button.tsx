import { MessageCircle } from 'lucide-react';
import { cn } from '@/lib/utils';

type Props = {
    number: string | null;
    message: string;
    label: string;
    className?: string;
};

/**
 * wa.me wants the number in international form with no plus sign. People
 * type numbers the way they dial them -- 09xx locally, 00963 or +963 from
 * abroad -- so the local and 00 forms are turned into the E.164 digits.
 * Syria is the default country, as everywhere else in the app.
 */
export function whatsappNumber(number: string): string {
    const digits = number.replace(/\D/g, '');

    if (digits.startsWith('00')) {
        return digits.slice(2);
    }

    if (digits.startsWith('0')) {
        return `963${digits.slice(1)}`;
    }

    return digits;
}

/**
 * Deep link into WhatsApp. Renders nothing when the venue has not published a
 * number, rather than showing a dead button.
 */
export function WhatsAppButton({ number, message, label, className }: Props) {
    if (!number) {
        return null;
    }

    const href = `https://wa.me/${whatsappNumber(number)}?text=${encodeURIComponent(message)}`;

    return (
        <a
            href={href}
            target="_blank"
            rel="noopener noreferrer"
            className={cn(
                'flex items-center justify-center gap-2 rounded-xl bg-[#25D366] py-3 font-medium text-white transition hover:brightness-95',
                className,
            )}
        >
            <MessageCircle className="size-5" />
            {label}
        </a>
    );
}
