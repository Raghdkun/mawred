import { Check } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/utils';

const STEPS = ['upload', 'map', 'validate', 'confirm'] as const;
export type ImportStep = (typeof STEPS)[number];

/** Upload → Map columns → Validate → Confirm (handoff 9). Flows in the reading direction. */
export function ImportStepper({ current }: { current: ImportStep }) {
    const { t } = useTranslation('imports');
    const index = STEPS.indexOf(current);

    return (
        <ol aria-label={t('steps.label')} className="mb-6 flex items-center gap-2">
            {STEPS.map((step, i) => (
                <li
                    key={step}
                    aria-current={i === index ? 'step' : undefined}
                    className="flex flex-1 items-center gap-2"
                >
                    <span
                        className={cn(
                            'flex size-7 shrink-0 items-center justify-center rounded-full border text-caption font-semibold',
                            i < index && 'border-primary bg-primary text-primary-foreground',
                            i === index && 'border-primary text-primary',
                            i > index && 'border-border text-muted-foreground',
                        )}
                    >
                        {i < index ? <Check aria-hidden className="size-4" /> : i + 1}
                    </span>
                    <span
                        className={cn(
                            'text-sm whitespace-nowrap',
                            i === index ? 'font-medium text-foreground' : 'text-text-secondary',
                        )}
                    >
                        {t(`steps.${step}`)}
                    </span>
                    {i < STEPS.length - 1 ? (
                        <span
                            aria-hidden
                            className={cn('h-px flex-1', i < index ? 'bg-primary' : 'bg-border')}
                        />
                    ) : null}
                </li>
            ))}
        </ol>
    );
}

export function DesktopOnly() {
    const { t } = useTranslation('imports');

    return (
        <div className="rounded-lg bg-card p-6 text-center shadow-sm md:hidden">
            <p className="text-body font-semibold text-foreground">{t('desktopOnly.title')}</p>
            <p className="mt-1 text-small text-muted-foreground">{t('desktopOnly.body')}</p>
        </div>
    );
}
