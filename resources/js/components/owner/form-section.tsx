import { ChevronDown } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';

/**
 * One foldable group of fields.
 *
 * The event form is the longest screen in the product -- details, pricing,
 * dates, status, location, cover, rules and inclusions in one uninterrupted
 * scroll -- and an owner creating their first event met all of it at once.
 * The three groups an event cannot exist without stay open; everything else
 * starts folded, so a first event is a handful of fields and the rest is
 * there when it is wanted.
 *
 * Kept mounted when closed: these are real form fields, and unmounting them
 * would silently drop whatever had been typed into a section before it was
 * folded away.
 */
export function FormSection({
    title,
    hint,
    badge,
    defaultOpen = false,
    children,
}: {
    title: string;
    hint?: string;
    /** A count worth seeing without opening the section. */
    badge?: number;
    defaultOpen?: boolean;
    children: ReactNode;
}) {
    return (
        <Collapsible
            defaultOpen={defaultOpen}
            className="group/section rounded-xl border"
        >
            <CollapsibleTrigger className="flex w-full cursor-pointer items-center gap-3 px-4 py-3 text-start">
                <span className="flex-1">
                    <span className="block text-sm font-semibold">{title}</span>
                    {hint && (
                        <span className="mt-0.5 block text-xs text-muted-foreground">
                            {hint}
                        </span>
                    )}
                </span>

                {badge !== undefined && badge > 0 && (
                    <span className="rounded-full bg-muted px-2 py-0.5 text-xs font-medium tabular-nums">
                        {badge}
                    </span>
                )}

                {/* Physical rotation, deliberately: the chevron follows the
                    open state, not the document direction. */}
                <ChevronDown
                    aria-hidden="true"
                    className="size-4 shrink-0 text-muted-foreground transition-transform duration-200 group-data-[state=open]/section:rotate-180"
                />
            </CollapsibleTrigger>

            <CollapsibleContent
                forceMount
                className="data-[state=closed]:hidden"
            >
                <div className="border-t p-4">{children}</div>
            </CollapsibleContent>
        </Collapsible>
    );
}
