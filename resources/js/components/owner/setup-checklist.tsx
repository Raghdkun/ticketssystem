import { Link } from '@inertiajs/react';
import { Check, Circle } from 'lucide-react';
import { useTranslation } from '@/lib/translation';

export type SetupSteps = {
    location: boolean;
    event: boolean;
    published: boolean;
    staff: boolean;
};

const STEPS = [
    { key: 'location', href: '/owner/locations' },
    { key: 'event', href: '/owner/events/create' },
    { key: 'published', href: '/owner/events' },
    { key: 'staff', href: '/owner/staff', optional: true },
] as const;

/**
 * What to do first, for a venue that has never published anything.
 *
 * The invitation flow sets a venue up and then leaves the owner on a
 * dashboard of zeroes with no next step. This is that next step, and it
 * disappears on its own the moment an event goes live -- a checklist that
 * lingers after it is finished is just clutter.
 */
export function SetupChecklist({ steps }: { steps: SetupSteps }) {
    const t = useTranslation();
    const done = STEPS.filter((step) => steps[step.key]).length;

    return (
        <section className="brand-surface rounded-2xl border p-5 sm:p-6">
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <h2 className="font-semibold">{t('dash.setup_title')}</h2>
                <p className="text-sm text-muted-foreground tabular-nums">
                    {t('dash.setup_progress', {
                        done,
                        total: STEPS.length,
                    })}
                </p>
            </div>

            <p className="mt-1 text-sm text-muted-foreground">
                {t('dash.setup_hint')}
            </p>

            <ol className="mt-4 space-y-1">
                {STEPS.map((step) => {
                    const complete = steps[step.key];

                    return (
                        <li key={step.key}>
                            <Link
                                href={step.href}
                                className="flex min-h-11 items-start gap-3 rounded-lg px-2 py-2 transition-colors hover:bg-muted/60"
                            >
                                {/* State is an icon plus the text weight,
                                    never colour alone. */}
                                {complete ? (
                                    <Check
                                        aria-hidden="true"
                                        className="mt-0.5 size-4 shrink-0 text-primary"
                                    />
                                ) : (
                                    <Circle
                                        aria-hidden="true"
                                        className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                    />
                                )}

                                <span className="min-w-0 flex-1">
                                    <span
                                        className={
                                            complete
                                                ? 'block text-sm text-muted-foreground line-through'
                                                : 'block text-sm font-medium'
                                        }
                                    >
                                        {t(`dash.setup.${step.key}`)}
                                    </span>
                                    <span className="mt-0.5 block text-xs text-muted-foreground">
                                        {t(`dash.setup.${step.key}_hint`)}
                                    </span>
                                </span>

                                <span className="sr-only">
                                    {complete
                                        ? t('dash.setup_done')
                                        : t('dash.setup_todo')}
                                </span>
                            </Link>
                        </li>
                    );
                })}
            </ol>
        </section>
    );
}
