import { useReducedMotion } from 'motion/react';
import BlurText from '@/components/bits/blur-text';

/**
 * A heading whose words settle in from a soft blur.
 *
 * React Bits' BlurText, held to the house rules: the text is always
 * readable (it starts at half opacity, never invisible), it animates
 * transform and opacity plus a short blur, and under reduced motion it is
 * plain text. Arabic splits by words, never letters -- letters would break
 * the joins between them.
 */
export function BlurHeading({
    text,
    className,
}: {
    text: string;
    className?: string;
}) {
    const reduceMotion = useReducedMotion();

    if (reduceMotion) {
        return <span className={className}>{text}</span>;
    }

    return (
        <BlurText
            text={text}
            animateBy="words"
            direction="bottom"
            delay={70}
            stepDuration={0.28}
            animationFrom={{ filter: 'blur(8px)', opacity: 0.5, y: 14 }}
            animationTo={[
                { filter: 'blur(3px)', opacity: 0.8, y: 4 },
                { filter: 'blur(0px)', opacity: 1, y: 0 },
            ]}
            className={className ?? ''}
        />
    );
}
