import { Head, usePage } from '@inertiajs/react';
import { motion, useReducedMotion } from 'motion/react';
import { useTranslation } from 'react-i18next';
import {
    AdminStep,
    BranchStep,
    CompanyStep,
    LanguagesStep,
    LaterStep,
    PoliciesStep,
    type PolicySummary,
    ReviewStep,
    stepIsLater,
    WelcomeStep,
} from '@/features/setup/steps';
import { type Step, type StepKey } from '@/features/setup/types';
import { CompactStepper, WizardRail } from '@/features/setup/wizard-rail';
import { useLocale } from '@/hooks/use-locale';
import { pageTransition } from '@/lib/motion';

type Props = {
    current: StepKey;
    complete: boolean;
    steps: Step[];
    company: { name: { en: string; ar: string }; legalName: string | null; accent: string | null };
    languages: { defaultLocale: string; defaultDigits: string; hijriByDefault: boolean };
    admin: { name: string; email: string; twoFactorEnabled: boolean };
    branch: App.Domain.Organization.Data.BranchData | null;
    policies: PolicySummary;
};

/** Setup wizard (handoff 2): rail 300 px + form 640 px + live preview; compact bar on smaller screens. */
export default function SetupWizard({
    current,
    complete,
    steps,
    company,
    languages,
    admin,
    branch,
    policies,
}: Props) {
    const { t } = useTranslation('setup');
    const { company: shared } = usePage().props;
    const { dir } = useLocale();
    const reduced = useReducedMotion() ?? false;
    const step = steps.find((s) => s.key === current);
    const transition = pageTransition(dir, reduced);

    const body = (() => {
        if (stepIsLater(step) && step) return <LaterStep step={step} />;

        switch (current) {
            case 'welcome':
                return <WelcomeStep />;
            case 'company':
                return <CompanyStep company={company} logoUrl={shared.logoUrl} />;
            case 'languages':
                return <LanguagesStep languages={languages} />;
            case 'branch':
                return <BranchStep branch={branch} />;
            case 'policies':
                return <PoliciesStep policies={policies} />;
            case 'admin':
                return <AdminStep admin={admin} />;
            case 'review':
                return <ReviewStep steps={steps} complete={complete} />;
            default:
                return null;
        }
    })();

    return (
        <>
            <Head title={t('pageTitle')} />
            <div className="mx-auto flex w-full max-w-[1280px] flex-1 flex-col gap-6 px-4 py-6 md:px-8 lg:flex-row lg:gap-12 lg:py-10">
                <aside className="lg:w-[300px] lg:shrink-0">
                    <div className="lg:hidden">
                        <CompactStepper steps={steps} current={current} />
                    </div>
                    <div className="hidden lg:sticky lg:top-10 lg:block">
                        <WizardRail steps={steps} current={current} />
                    </div>
                </aside>
                <motion.section
                    key={current}
                    initial="initial"
                    animate="animate"
                    variants={transition.variants}
                    transition={transition.transition}
                    className="min-w-0 flex-1"
                >
                    {body}
                </motion.section>
            </div>
        </>
    );
}
