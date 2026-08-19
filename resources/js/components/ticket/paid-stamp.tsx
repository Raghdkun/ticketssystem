import { CheckCircle2 } from 'lucide-react';
import { AnimatePresence, motion, useReducedMotion } from 'motion/react';

type Props = {
    show: boolean;
    /** Animate in dramatically (a live flip) vs. appear settled (page load). */
    animate: boolean;
    label: string;
};

/**
 * The validation stamp that lands when a ticket is marked paid.
 *
 * Sized and placed to read as ink pressed onto the ticket: angled, bounded so
 * it never bleeds off the card, and sitting above the content rather than
 * behind it so it does not look like a background texture.
 *
 * Deliberately straddles the perforation rather than the QR: a paid ticket is
 * still scanned at the door, and covering a finder pattern would break it.
 */
export function PaidStamp({ show, animate, label }: Props) {
    const reduceMotion = useReducedMotion();
    const settled = { opacity: 1, scale: 1, rotate: -12 };

    return (
        <AnimatePresence>
            {show && (
                <motion.div
                    className="pointer-events-none absolute inset-x-0 top-[19%] z-30 flex justify-center"
                    initial={
                        reduceMotion || !animate
                            ? settled
                            : { opacity: 0, scale: 2.6, rotate: -38 }
                    }
                    animate={settled}
                    exit={{ opacity: 0, scale: 0.85 }}
                    transition={
                        reduceMotion || !animate
                            ? { duration: 0 }
                            : {
                                  type: 'spring',
                                  stiffness: 280,
                                  damping: 15,
                                  mass: 0.8,
                              }
                    }
                    aria-hidden="true"
                >
                    <div className="flex items-center gap-2 rounded-lg border-4 border-emerald-600 bg-white/85 px-4 py-1.5 text-emerald-700 shadow-lg backdrop-blur-[1px] dark:border-emerald-400 dark:bg-neutral-900/85 dark:text-emerald-300">
                        <CheckCircle2 className="size-6" strokeWidth={3} />
                        <span className="text-2xl font-black tracking-wider uppercase">
                            {label}
                        </span>
                    </div>
                </motion.div>
            )}
        </AnimatePresence>
    );
}
