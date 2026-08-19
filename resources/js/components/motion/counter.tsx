import { animate, useReducedMotion } from 'motion/react';
import { useEffect, useRef, useState } from 'react';

/**
 * Counts up to a value on mount.
 *
 * The reduced-motion case is derived during render rather than written from an
 * effect, so there is no extra render pass and no synchronous setState in an
 * effect. The element always contains the real number, so assistive tech and
 * copy-paste are unaffected by the animation.
 */
export function Counter({
    value,
    className,
}: {
    value: number;
    className?: string;
}) {
    const reduced = useReducedMotion();
    const [animated, setAnimated] = useState(0);
    const previous = useRef(0);

    useEffect(() => {
        if (reduced) {
            return;
        }

        const controls = animate(previous.current, value, {
            duration: 0.7,
            ease: [0.22, 1, 0.36, 1],
            onUpdate: (latest) => setAnimated(Math.round(latest)),
        });

        previous.current = value;

        return () => controls.stop();
    }, [value, reduced]);

    const display = reduced ? value : animated;

    return (
        <span className={className} aria-label={String(value)}>
            {display.toLocaleString()}
        </span>
    );
}
