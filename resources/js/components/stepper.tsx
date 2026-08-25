import { Minus, Plus } from 'lucide-react';
import { useTranslation } from '@/lib/translation';
import { cn } from '@/lib/utils';

type Props = {
    value: number;
    onChange: (value: number) => void;
    min?: number;
    max: number;
    /** Rendered into a hidden input so the value posts with a plain form. */
    name?: string;
    label?: string;
    className?: string;
};

/**
 * Seat count.
 *
 * A number input on a phone opens a numeric keypad over the form and needs a
 * dismiss before you can carry on. Two 52px targets do the same job with a
 * thumb, which is how this is actually used — at a door, one-handed.
 *
 * The buttons are deliberately not `type="submit"`; inside a form an
 * unqualified button submits it.
 */
export function Stepper({
    value,
    onChange,
    min = 1,
    max,
    name,
    label,
    className,
}: Props) {
    const t = useTranslation();
    const clamp = (next: number) => Math.max(min, Math.min(max, next));

    return (
        <div className={cn('flex items-center gap-3', className)}>
            <button
                type="button"
                onClick={() => onChange(clamp(value - 1))}
                disabled={value <= min}
                aria-label={t('common.decrease')}
                className="flex size-13 shrink-0 cursor-pointer items-center justify-center rounded-full border border-border bg-card text-foreground transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-40"
            >
                <Minus className="size-5" />
            </button>

            <output
                aria-live="polite"
                aria-label={label}
                className="min-w-14 text-center font-display text-3xl font-semibold tabular-nums"
            >
                {value}
            </output>

            <button
                type="button"
                onClick={() => onChange(clamp(value + 1))}
                disabled={value >= max}
                aria-label={t('common.increase')}
                className="flex size-13 shrink-0 cursor-pointer items-center justify-center rounded-full border border-border bg-card text-foreground transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-40"
            >
                <Plus className="size-5" />
            </button>

            {name && <input type="hidden" name={name} value={value} />}
        </div>
    );
}
