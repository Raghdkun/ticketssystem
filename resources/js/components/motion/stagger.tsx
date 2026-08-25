import { motion, useReducedMotion } from 'motion/react';
import type { ReactNode } from 'react';
import { respectMotion, rise, staggerContainer } from '@/lib/motion';

/**
 * Reveals children in sequence. Purely presentational: the children render
 * regardless, so nothing depends on the animation completing.
 *
 * Items rise without fading, so a stagger that fails to run leaves readable
 * content behind rather than an empty rectangle.
 */
export function Stagger({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    const reduced = useReducedMotion();

    return (
        <motion.div
            className={className}
            variants={respectMotion(staggerContainer, reduced)}
            initial="hidden"
            animate="visible"
        >
            {children}
        </motion.div>
    );
}

export function StaggerItem({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    const reduced = useReducedMotion();

    return (
        <motion.div
            className={className}
            variants={respectMotion(rise, reduced)}
        >
            {children}
        </motion.div>
    );
}
