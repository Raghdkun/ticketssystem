import type { ReactNode } from 'react';
import { useRef } from 'react';
import HoldButton from '@/components/bits/hold-button';

/**
 * Hold to confirm, in place of a second click.
 *
 * React Bits' HoldButton wired to the form it sits in: a completed hold
 * submits the closest form, so the server sees an ordinary POST or DELETE.
 * Coloured from the tokens, no glow (the one permitted shadow is
 * shadow-raised), and the house radius for anything pressed.
 */
export function HoldSubmit({
    children,
    doneLabel,
    disabled = false,
    tone = 'destructive',
}: {
    children: ReactNode;
    doneLabel: ReactNode;
    disabled?: boolean;
    tone?: 'destructive' | 'primary';
}) {
    const anchor = useRef<HTMLSpanElement>(null);

    return (
        <span ref={anchor} className="inline-block">
            <HoldButton
                holdTime={1200}
                releaseTime={180}
                resetAfter={4000}
                radius={10}
                glow={false}
                wave={false}
                backgroundColor="var(--muted)"
                textColor="var(--foreground)"
                fillColor={
                    tone === 'destructive'
                        ? 'var(--destructive)'
                        : 'var(--primary)'
                }
                fillTextColor={
                    tone === 'destructive'
                        ? 'var(--destructive-foreground)'
                        : 'var(--primary-foreground)'
                }
                doneLabel={doneLabel}
                disabled={disabled}
                onHold={() => anchor.current?.closest('form')?.requestSubmit()}
                className="min-h-11 font-extrabold"
            >
                {children}
            </HoldButton>
        </span>
    );
}
