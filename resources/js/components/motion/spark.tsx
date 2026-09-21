import { useReducedMotion } from 'motion/react';
import type { ReactNode } from 'react';
import ClickSpark from '@/components/bits/click-spark';

/**
 * A burst of orange sparks where a decisive button is pressed.
 *
 * Wraps React Bits' ClickSpark for the few moments that deserve it:
 * accepting the terms, publishing an event, accepting an offer. Under
 * reduced motion the children render alone.
 */
export function Spark({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    const reduceMotion = useReducedMotion();

    if (reduceMotion) {
        return <div className={className}>{children}</div>;
    }

    return (
        <div className={className}>
            <ClickSpark
                sparkColor="#F66002"
                sparkSize={9}
                sparkRadius={24}
                sparkCount={10}
                duration={450}
            >
                {children}
            </ClickSpark>
        </div>
    );
}
