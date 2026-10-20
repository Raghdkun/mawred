import { AlertCircle, Check } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/utils';
import { STEPS, type Step } from './definition';

/**
 * Dataset → Fields → Filters → Group → Visualize (handoff 37). Every step stays reachable once
 * the data is chosen; a step the server flagged shows a warning mark.
 */
export function BuilderStepper({
    current,
    onSelect,
    reachable,
    flagged,
}: {
    current: Step;
    onSelect: (step: Step) => void;
    reachable: boolean;
    flagged: Set<Step>;
}) {
    const { t } = useTranslation('reports');
    const index = STEPS.indexOf(current);

    return (
        <nav aria-label={t('builder.steps.label')}>
            <ol className="flex items-center gap-2">
                {STEPS.map((step, i) => {
                    const enabled = step === 'dataset' || reachable;

                    return (
                        <li key={step} className="flex flex-1 items-center gap-2">
                            <button
                                type="button"
                                disabled={!enabled}
                                onClick={() => onSelect(step)}
                                aria-current={i === index ? 'step' : undefined}
                                className="flex min-h-11 items-center gap-2 rounded-md px-1 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:cursor-not-allowed disabled:opacity-60"
                                data-builder-step={step}
                            >
                                <span
                                    className={cn(
                                        'flex size-7 shrink-0 items-center justify-center rounded-full border text-caption font-semibold',
                                        flagged.has(step) && 'border-danger text-danger',
                                        !flagged.has(step) &&
                                            i < index &&
                                            'border-primary bg-primary text-primary-foreground',
                                        !flagged.has(step) &&
                                            i === index &&
                                            'border-primary text-primary',
                                        !flagged.has(step) &&
                                            i > index &&
                                            'border-border text-muted-foreground',
                                    )}
                                >
                                    {flagged.has(step) ? (
                                        <AlertCircle aria-hidden className="size-4" />
                                    ) : i < index ? (
                                        <Check aria-hidden className="size-4" />
                                    ) : (
                                        i + 1
                                    )}
                                </span>
                                <span
                                    className={cn(
                                        'text-small whitespace-nowrap',
                                        i === index
                                            ? 'font-medium text-foreground'
                                            : 'text-text-secondary',
                                    )}
                                >
                                    {t(`builder.steps.${step}`)}
                                </span>
                            </button>
                            {i < STEPS.length - 1 ? (
                                <span
                                    aria-hidden
                                    className={cn(
                                        'h-px flex-1',
                                        i < index ? 'bg-primary' : 'bg-border',
                                    )}
                                />
                            ) : null}
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
