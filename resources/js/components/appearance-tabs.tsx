import type { LucideIcon } from 'lucide-react';
import { Monitor, Moon, Sun } from 'lucide-react';
import type { HTMLAttributes } from 'react';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { useTranslation } from '@/lib/translation';
import { cn } from '@/lib/utils';

/**
 * Light / dark / system. Built from the design tokens like every other
 * control, so the switch that changes the theme is itself themed.
 */
export default function AppearanceToggleTab({
    className = '',
    ...props
}: HTMLAttributes<HTMLDivElement>) {
    const { appearance, updateAppearance } = useAppearance();
    const t = useTranslation();

    const tabs: { value: Appearance; icon: LucideIcon; label: string }[] = [
        { value: 'light', icon: Sun, label: t('settings.appearance_light') },
        { value: 'dark', icon: Moon, label: t('settings.appearance_dark') },
        {
            value: 'system',
            icon: Monitor,
            label: t('settings.appearance_system'),
        },
    ];

    return (
        <div
            role="group"
            aria-label={t('settings.appearance')}
            className={cn(
                'inline-flex gap-1 rounded-md bg-muted p-1',
                className,
            )}
            {...props}
        >
            {tabs.map(({ value, icon: Icon, label }) => (
                <button
                    key={value}
                    type="button"
                    aria-pressed={appearance === value}
                    onClick={() => updateAppearance(value)}
                    className={cn(
                        'flex min-h-9 items-center rounded-md px-3.5 py-1.5 text-sm transition-colors',
                        appearance === value
                            ? 'border bg-card font-extrabold text-foreground'
                            : 'text-muted-foreground hover:bg-accent hover:text-foreground',
                    )}
                >
                    <Icon className="-ms-1 size-4" />
                    <span className="ms-1.5">{label}</span>
                </button>
            ))}
        </div>
    );
}
