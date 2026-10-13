import { Link } from '@inertiajs/react';
import { Check, List } from 'lucide-react';
import { motion, useReducedMotion } from 'motion/react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    Drawer,
    DrawerContent,
    DrawerHeader,
    DrawerTitle,
    DrawerTrigger,
} from '@/components/ui/drawer';
import { useLocale } from '@/hooks/use-locale';
import { formatNumber } from '@/lib/format';
import { durations, easeSoft } from '@/lib/motion';
import { cn } from '@/lib/utils';
import setup from '@/routes/setup';
import { type Step, type StepKey } from './types';

function Bullet({ step, current }: { step: Step; current: boolean }) {
    const { locale } = useLocale();

    if (step.status === 'completed') {
        return (
            <span className="relative z-10 flex size-7 items-center justify-center rounded-full bg-water text-white dark:text-primary-foreground">
                <Check aria-hidden className="size-4" strokeWidth={2.5} />
            </span>
        );
    }

    return (
        <span
            className={cn(
                'relative z-10 flex size-7 items-center justify-center rounded-full bg-card text-caption font-semibold',
                current &&
                    'border-2 border-water text-water shadow-[0_0_0_4px_color-mix(in_oklab,var(--water)_20%,transparent)]',
                !current &&
                    step.status === 'skipped' &&
                    'border-[1.5px] border-dashed border-input-hover text-muted-foreground',
                !current &&
                    step.status === 'pending' &&
                    'border-[1.5px] border-input text-muted-foreground',
            )}
        >
            {formatNumber(step.number, locale)}
        </span>
    );
}

/** Desktop side rail (DSNavigation wizard): resumable, skipped steps keep a dashed bullet. */
export function WizardRail({ steps, current }: { steps: Step[]; current: StepKey }) {
    const { t } = useTranslation('setup');
    const reduced = useReducedMotion() ?? false;
    const done = steps.filter((step) => step.status !== 'pending').length;
    const fill = steps.length > 1 ? Math.min(done, steps.length - 1) / (steps.length - 1) : 0;

    return (
        <nav aria-label={t('railLabel')} className="relative">
            {/* Connector that fills like water (400 ms per step; instant when motion is reduced). */}
            <span
                aria-hidden
                className="absolute start-[13px] top-3.5 bottom-3.5 w-0.5 rounded-full bg-border"
            />
            <motion.span
                aria-hidden
                className="absolute start-[13px] top-3.5 w-0.5 origin-top rounded-full bg-water"
                initial={false}
                animate={{ height: `calc((100% - 28px) * ${fill})` }}
                transition={
                    reduced ? { duration: 0 } : { duration: durations.page, ease: easeSoft }
                }
            />
            <ol className="relative flex flex-col gap-0">
                {steps.map((step) => {
                    const isCurrent = step.key === current;

                    return (
                        <li key={step.key}>
                            <Link
                                href={setup.show(step.key)}
                                aria-current={isCurrent ? 'step' : undefined}
                                className="group grid grid-cols-[28px_minmax(0,1fr)] items-start gap-3.5 rounded-md pb-[18px] focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <Bullet step={step} current={isCurrent} />
                                <span className="flex flex-col pt-0.5">
                                    <span
                                        className={cn(
                                            'text-sm',
                                            isCurrent
                                                ? 'font-medium text-foreground'
                                                : 'text-text-secondary group-hover:text-foreground',
                                        )}
                                    >
                                        {t(`steps.${step.key}`)}
                                    </span>
                                    <span className="text-caption text-muted-foreground">
                                        {isCurrent
                                            ? t('status.current')
                                            : t(`status.${step.status}`)}
                                        {step.required ? ` · ${t('status.required')}` : null}
                                    </span>
                                </span>
                            </Link>
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}

/** Phones and tablets: segmented bar; tap to open the full list in a bottom sheet. */
export function CompactStepper({ steps, current }: { steps: Step[]; current: StepKey }) {
    const { t } = useTranslation('setup');
    const { locale } = useLocale();
    const index = steps.findIndex((step) => step.key === current);

    return (
        <div className="flex flex-col gap-2">
            <div aria-hidden className="flex gap-1">
                {steps.map((step) => (
                    <span
                        key={step.key}
                        className={cn(
                            'h-1.5 flex-1 rounded-full',
                            step.status === 'completed' && 'bg-water',
                            step.key === current && step.status !== 'completed' && 'bg-water/50',
                            step.status === 'skipped' &&
                                step.key !== current &&
                                'border border-dashed border-input-hover',
                            step.status === 'pending' && step.key !== current && 'bg-border',
                        )}
                    />
                ))}
            </div>
            <div className="flex items-center justify-between gap-2">
                <p className="text-small text-text-secondary">
                    {t('stepOf', {
                        current: formatNumber(index + 1, locale),
                        total: formatNumber(steps.length, locale),
                    })}
                    {' · '}
                    <span className="font-medium text-foreground">{t(`steps.${current}`)}</span>
                </p>
                <Drawer>
                    <DrawerTrigger asChild>
                        <Button variant="ghost" size="sm">
                            <List aria-hidden /> {t('allSteps')}
                        </Button>
                    </DrawerTrigger>
                    <DrawerContent>
                        <DrawerHeader>
                            <DrawerTitle>{t('railLabel')}</DrawerTitle>
                        </DrawerHeader>
                        <div className="max-h-[70vh] overflow-y-auto px-4 pb-6">
                            <WizardRail steps={steps} current={current} />
                        </div>
                    </DrawerContent>
                </Drawer>
            </div>
        </div>
    );
}
