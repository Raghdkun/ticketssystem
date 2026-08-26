import { useEffect, useRef } from 'react';
import type { ReactNode } from 'react';

/**
 * Softens Inertia navigations.
 *
 * The wrapper is never remounted or re-keyed — keying it would tear down the
 * whole Inertia tree on every visit. Instead the CSS animation is re-triggered
 * by hand on each navigate.
 *
 * A CSS keyframe rather than a Motion variant, deliberately: a keyframe's
 * resting state is the normal one, so if the animation never runs — throttled
 * tab, reduced motion, a stylesheet that failed — the page is simply there.
 * A JS variant would leave its `from` values pinned as inline styles.
 *
 * Opacity only, and this is not a style preference. A `transform` on this
 * wrapper would make it the containing block for every `position: fixed`
 * descendant, so the bottom tab bar would slide with the page each time.
 * Opacity creates a stacking context and nothing else.
 */
export function PageTransition({ children }: { children: ReactNode }) {
    const holder = useRef<HTMLDivElement>(null);

    useEffect(() => {
        // The DOM event rather than `router.on`: this one is verifiably
        // dispatched on every visit, and it does not depend on the router's
        // listener API staying the same shape across Inertia releases.
        const replay = () => {
            const element = holder.current;

            if (!element) {
                return;
            }

            element.classList.remove('page-enter');

            // Reading layout flushes the class removal, so re-adding it starts
            // a new animation rather than continuing the finished one. The
            // read has to be *used*: written as a bare `void element.
            // offsetWidth` the minifier deletes it as having no effect, and
            // the animation silently stops replaying in production builds
            // while still working in dev.
            if (element.offsetWidth >= 0) {
                element.classList.add('page-enter');
            }
        };

        document.addEventListener('inertia:navigate', replay);

        return () => document.removeEventListener('inertia:navigate', replay);
    }, []);

    return (
        <div ref={holder} className="page-enter">
            {children}
        </div>
    );
}
