import type { Transition, Variants } from 'motion/react';

/**
 * Shared motion vocabulary.
 *
 * Two rules hold everywhere: only transform and opacity are animated (they run
 * on the compositor and never trigger layout), and durations stay in the
 * 150-300ms band for feedback, a little longer for entrances.
 */
export const EASE_OUT: Transition['ease'] = [0.22, 1, 0.36, 1];

/**
 * Spring presets. Linear easing reads as mechanical; springs give interactive
 * elements a sense of weight, which is what makes a UI feel physical.
 */
export const SPRING = {
    /** Snappy, for taps and toggles. */
    press: { type: 'spring', stiffness: 400, damping: 28, mass: 0.6 },
    /** Softer, for panels and cards settling into place. */
    settle: { type: 'spring', stiffness: 220, damping: 26, mass: 0.9 },
} as const;

export const DURATION = {
    feedback: 0.18,
    entrance: 0.42,
    emphasis: 0.6,
} as const;

/** Container that reveals its children one after another. */
export const staggerContainer: Variants = {
    hidden: {},
    visible: {
        transition: { staggerChildren: 0.05, delayChildren: 0.04 },
    },
};

/**
 * Rises into place. Opacity starts at 1 for content that must never be
 * invisible if motion fails to run; use `fadeRise` where a fade is safe.
 */
export const rise: Variants = {
    hidden: { y: 12 },
    visible: {
        y: 0,
        transition: { duration: DURATION.entrance, ease: EASE_OUT },
    },
};

export const fadeRise: Variants = {
    hidden: { opacity: 0, y: 12 },
    visible: {
        opacity: 1,
        y: 0,
        transition: { duration: DURATION.entrance, ease: EASE_OUT },
    },
};

/**
 * Variants collapse to a no-op when the user prefers reduced motion, so a
 * single call site covers both cases.
 */
export function respectMotion(
    variants: Variants,
    reduced: boolean | null,
): Variants {
    if (!reduced) {
        return variants;
    }

    return {
        hidden: {},
        visible: { transition: { duration: 0 } },
    };
}
