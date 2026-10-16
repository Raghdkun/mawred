import { Head, Link, router } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Field } from '@/components/mw/field';
import { PageHeader, Section } from '@/components/mw/page-header';
import { SwitchField } from '@/components/mw/switch-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { AutomationEditor } from '@/features/hiring/automation-editor';
import { BilingualText, type Pair } from '@/features/hiring/bilingual-text';
import { CriteriaEditor } from '@/features/hiring/criteria-editor';
import { StageEditor } from '@/features/hiring/stage-editor';
import forms from '@/routes/forms';
import hiring from '@/routes/hiring';

type Props = {
    stages: App.Domain.Hiring.Data.StageData[];
    automationPeople: Record<string, string>;
    criteria: Array<{ id: string; name: { en: string; ar: string } }>;
    careersIntro: Pair;
    privacyNotice: Pair;
    retentionMonths: number;
    poolRetentionMonths: number;
    rejectionMessage: { enabled: boolean; subject: Pair; body: Pair };
    offerLetter: Pair;
    letterVariables: string[];
    careersUrl: string;
    defaultFormId: string | null;
};

/**
 * Hiring settings: the default pipeline and its automations (D93), default scorecard criteria
 * (D90), the careers texts (D87), retention (D86), the rejection message and the offer letter
 * template (D95).
 */
export default function HiringSettings(props: Props) {
    const { t } = useTranslation('hiring');
    const [intro, setIntro] = useState(props.careersIntro);
    const [notice, setNotice] = useState(props.privacyNotice);
    const [retention, setRetention] = useState(String(props.retentionMonths));
    const [poolRetention, setPoolRetention] = useState(String(props.poolRetentionMonths));
    const [rejectionOn, setRejectionOn] = useState(props.rejectionMessage.enabled);
    const [rejectionSubject, setRejectionSubject] = useState(props.rejectionMessage.subject);
    const [rejectionBody, setRejectionBody] = useState(props.rejectionMessage.body);
    const [letter, setLetter] = useState(props.offerLetter);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);

    const save = () => {
        setSaving(true);
        router.put(
            hiring.settings.update.url(),
            {
                careersIntro: intro,
                privacyNotice: notice,
                retentionMonths: Number(retention),
                poolRetentionMonths: Number(poolRetention),
                rejectionMessage: {
                    enabled: rejectionOn,
                    subject: rejectionSubject,
                    body: rejectionBody,
                },
                offerLetter: letter,
            },
            {
                preserveScroll: true,
                onError: setErrors,
                onSuccess: () => setErrors({}),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <>
            <Head title={t('settings.title')} />
            <PageHeader
                title={t('settings.title')}
                description={t('settings.description')}
                actions={
                    <Button asChild variant="outline">
                        <a href={props.careersUrl} target="_blank" rel="noreferrer">
                            <ExternalLink aria-hidden />
                            {t('settings.viewCareers')}
                        </a>
                    </Button>
                }
            />
            <div className="flex max-w-4xl flex-col gap-8">
                <Section title={t('settings.pipeline')} description={t('settings.pipelineHelp')}>
                    <StageEditor
                        stages={props.stages}
                        saveUrl={hiring.settings.pipeline.url()}
                        idPrefix="template"
                    />
                </Section>

                <Section title={t('automations.title')} description={t('settings.automationsHelp')}>
                    <AutomationEditor stages={props.stages} people={props.automationPeople} />
                </Section>

                <Section title={t('criteria.title')} description={t('settings.criteriaHelp')}>
                    <CriteriaEditor
                        criteria={props.criteria}
                        saveUrl={hiring.settings.criteria.url()}
                    />
                </Section>

                <form
                    className="flex flex-col gap-8"
                    onSubmit={(e) => {
                        e.preventDefault();
                        save();
                    }}
                >
                    <Section title={t('settings.careers')} description={t('settings.careersHelp')}>
                        <div className="flex flex-col gap-4">
                            <BilingualText
                                id="intro"
                                label={t('settings.intro')}
                                value={intro}
                                onChange={setIntro}
                                errors={errors}
                                errorKey="careersIntro"
                                required
                                multiline
                                maxLength={600}
                            />
                            <BilingualText
                                id="notice"
                                label={t('settings.notice')}
                                value={notice}
                                onChange={setNotice}
                                errors={errors}
                                errorKey="privacyNotice"
                                required
                                multiline
                                maxLength={2000}
                                hint={t('settings.noticeHint')}
                            />
                            {props.defaultFormId ? (
                                <Link
                                    href={forms.builder.url(props.defaultFormId)}
                                    className="inline-flex min-h-11 items-center self-start text-small text-primary hover:underline"
                                >
                                    {t('settings.editForm')}
                                </Link>
                            ) : null}
                        </div>
                    </Section>

                    <Section
                        title={t('settings.retention')}
                        description={t('settings.retentionHelp')}
                    >
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                id="retention"
                                label={t('settings.retentionMonths')}
                                error={errors.retentionMonths}
                            >
                                <Input
                                    id="retention"
                                    type="number"
                                    min={1}
                                    max={120}
                                    className="min-h-11"
                                    value={retention}
                                    onChange={(e) => setRetention(e.target.value)}
                                />
                            </Field>
                            <Field
                                id="pool-retention"
                                label={t('settings.poolRetentionMonths')}
                                error={errors.poolRetentionMonths}
                            >
                                <Input
                                    id="pool-retention"
                                    type="number"
                                    min={1}
                                    max={120}
                                    className="min-h-11"
                                    value={poolRetention}
                                    onChange={(e) => setPoolRetention(e.target.value)}
                                />
                            </Field>
                        </div>
                    </Section>

                    <Section
                        title={t('settings.rejection')}
                        description={t('settings.rejectionHelp')}
                    >
                        <div className="flex flex-col gap-4">
                            <SwitchField
                                id="rejection-on"
                                label={t('settings.rejectionOn')}
                                checked={rejectionOn}
                                onCheckedChange={setRejectionOn}
                            />
                            {rejectionOn ? (
                                <>
                                    <BilingualText
                                        id="rejection-subject"
                                        label={t('automations.subject')}
                                        value={rejectionSubject}
                                        onChange={setRejectionSubject}
                                        errors={errors}
                                        errorKey="rejectionMessage.subject"
                                        required
                                        maxLength={200}
                                    />
                                    <BilingualText
                                        id="rejection-body"
                                        label={t('automations.body')}
                                        value={rejectionBody}
                                        onChange={setRejectionBody}
                                        errors={errors}
                                        errorKey="rejectionMessage.body"
                                        required
                                        multiline
                                        maxLength={4000}
                                        hint={t('automations.variables')}
                                    />
                                </>
                            ) : null}
                        </div>
                    </Section>

                    <Section title={t('settings.letter')} description={t('settings.letterHelp')}>
                        <div className="flex flex-col gap-3">
                            <ul
                                className="flex flex-wrap gap-1"
                                aria-label={t('settings.letterVariables')}
                            >
                                {props.letterVariables.map((v) => (
                                    <li
                                        key={v}
                                        className="rounded-full bg-muted px-2 py-0.5 font-mono text-caption"
                                        dir="ltr"
                                    >
                                        {`{{ ${v} }}`}
                                    </li>
                                ))}
                            </ul>
                            <BilingualText
                                id="letter"
                                label={t('settings.letterTemplate')}
                                value={letter}
                                onChange={setLetter}
                                errors={errors}
                                errorKey="offerLetter"
                                required
                                multiline
                                maxLength={20000}
                            />
                        </div>
                    </Section>

                    <div className="flex justify-end">
                        <Button
                            type="submit"
                            className="min-h-11"
                            disabled={saving}
                            data-save-settings
                        >
                            {t('settings.save')}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
