import { Head, Link, router } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader, Section } from '@/components/mw/page-header';
import { Button } from '@/components/ui/button';
import { BilingualText, type Pair } from '@/features/hiring/bilingual-text';
import { StageEditor } from '@/features/hiring/stage-editor';
import forms from '@/routes/forms';
import hiring from '@/routes/hiring';

type Props = {
    stages: App.Domain.Hiring.Data.StageData[];
    careersIntro: Pair;
    privacyNotice: Pair;
    careersUrl: string;
    defaultFormId: string | null;
};

/** Hiring settings: the default pipeline new requisitions copy, and the careers page texts. */
export default function HiringSettings({
    stages,
    careersIntro,
    privacyNotice,
    careersUrl,
    defaultFormId,
}: Props) {
    const { t } = useTranslation('hiring');
    const [intro, setIntro] = useState(careersIntro);
    const [notice, setNotice] = useState(privacyNotice);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);

    return (
        <>
            <Head title={t('settings.title')} />
            <PageHeader
                title={t('settings.title')}
                description={t('settings.description')}
                actions={
                    <Button asChild variant="outline">
                        <a href={careersUrl} target="_blank" rel="noreferrer">
                            <ExternalLink aria-hidden />
                            {t('settings.viewCareers')}
                        </a>
                    </Button>
                }
            />
            <div className="flex max-w-4xl flex-col gap-8">
                <Section title={t('settings.pipeline')} description={t('settings.pipelineHelp')}>
                    <StageEditor
                        stages={stages}
                        saveUrl={hiring.settings.pipeline.url()}
                        idPrefix="template"
                    />
                </Section>

                <Section title={t('settings.careers')} description={t('settings.careersHelp')}>
                    <form
                        className="flex flex-col gap-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            setSaving(true);
                            router.put(
                                hiring.settings.update.url(),
                                { careersIntro: intro, privacyNotice: notice },
                                {
                                    preserveScroll: true,
                                    onError: setErrors,
                                    onFinish: () => setSaving(false),
                                },
                            );
                        }}
                    >
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
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            {defaultFormId ? (
                                <Link
                                    href={forms.builder.url(defaultFormId)}
                                    className="inline-flex min-h-11 items-center text-small text-primary hover:underline"
                                >
                                    {t('settings.editForm')}
                                </Link>
                            ) : (
                                <span />
                            )}
                            <Button type="submit" className="min-h-11" disabled={saving}>
                                {t('settings.save')}
                            </Button>
                        </div>
                    </form>
                </Section>
            </div>
        </>
    );
}
