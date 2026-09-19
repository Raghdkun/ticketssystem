import { Check, Monitor, Moon, Sun } from 'lucide-react';
import { AnimatePresence, motion, useReducedMotion } from 'motion/react';
import { useRef } from 'react';
import {
    ThemeAnimationType,
    useModeAnimation,
} from 'react-theme-switch-animation';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAppearance } from '@/hooks/use-appearance';
import type { Appearance } from '@/hooks/use-appearance';
import { useTranslation } from '@/lib/translation';
import { cn } from '@/lib/utils';

const OPTIONS: { value: Appearance; icon: typeof Sun; label: string }[] = [
    { value: 'light', icon: Sun, label: 'settings.appearance_light' },
    { value: 'dark', icon: Moon, label: 'settings.appearance_dark' },
    { value: 'system', icon: Monitor, label: 'settings.appearance_system' },
];

/**
 * Light / dark / system, beside the language switch.
 *
 * The trigger shows the mode the page is actually in; the menu offers the
 * three choices, because a visitor with no account has nowhere else to get
 * back to "system" once they have picked a side.
 *
 * The change itself is a circular reveal from the button, courtesy of
 * react-theme-switch-animation over the View Transitions API. The hook is
 * driven in controlled mode: our appearance store stays the source of truth
 * (it also writes the cookie the server render reads, and it knows about
 * "system", which the package does not). The hook only animates what the
 * store already decided. Reduced motion and browsers without view
 * transitions get an instant switch, by the package's own fallback.
 */
export function ThemeToggle({ className }: { className?: string }) {
    const t = useTranslation();
    const reduceMotion = useReducedMotion();
    const { appearance, resolvedAppearance, updateAppearance } =
        useAppearance();

    // What the user picked from the menu, read back when the reveal commits.
    // The package only knows "toggle"; this is how a choice of "system" that
    // happens to flip the resolved mode is persisted as "system", not "dark".
    const pending = useRef<Appearance | null>(null);

    const { ref, toggleSwitchTheme } = useModeAnimation({
        isDarkMode: resolvedAppearance === 'dark',
        animationType: ThemeAnimationType.CIRCLE,
        duration: 600,
        onDarkModeChange: (isDark) => {
            updateAppearance(pending.current ?? (isDark ? 'dark' : 'light'));
            pending.current = null;
        },
    });

    const choose = (mode: Appearance) => {
        if (mode === appearance) {
            return;
        }

        const willBeDark =
            mode === 'dark' ||
            (mode === 'system' &&
                window.matchMedia('(prefers-color-scheme: dark)').matches);

        // Only a change in what is on screen is worth animating; moving
        // from "dark" to "system" on a dark device just records the choice.
        if (willBeDark === (resolvedAppearance === 'dark')) {
            updateAppearance(mode);

            return;
        }

        pending.current = mode;
        void toggleSwitchTheme();
    };

    const Icon = resolvedAppearance === 'dark' ? Moon : Sun;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    ref={ref}
                    type="button"
                    aria-label={t('settings.appearance')}
                    className={cn(
                        'inline-flex size-11 cursor-pointer items-center justify-center rounded-lg bg-black/40 text-white backdrop-blur transition-colors duration-200 hover:bg-black/60',
                        className,
                    )}
                >
                    {/* The icon swaps with a small turn, so the button itself
                        acknowledges the change before the page does. */}
                    <AnimatePresence mode="wait" initial={false}>
                        <motion.span
                            key={resolvedAppearance}
                            initial={
                                reduceMotion
                                    ? false
                                    : { rotate: -90, scale: 0.6, opacity: 0 }
                            }
                            animate={{ rotate: 0, scale: 1, opacity: 1 }}
                            exit={
                                reduceMotion
                                    ? undefined
                                    : { rotate: 90, scale: 0.6, opacity: 0 }
                            }
                            transition={{ duration: reduceMotion ? 0 : 0.18 }}
                            className="flex"
                        >
                            <Icon className="size-4" aria-hidden="true" />
                        </motion.span>
                    </AnimatePresence>
                </button>
            </DropdownMenuTrigger>

            <DropdownMenuContent align="end">
                {OPTIONS.map(({ value, icon: OptionIcon, label }) => (
                    <DropdownMenuItem
                        key={value}
                        onSelect={() => choose(value)}
                        aria-checked={appearance === value}
                        role="menuitemradio"
                    >
                        <OptionIcon />
                        {t(label)}
                        {appearance === value && (
                            <Check className="ms-auto" aria-hidden="true" />
                        )}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
