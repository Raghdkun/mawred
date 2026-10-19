import { Head, Link, router, usePage } from '@inertiajs/react';
import { Briefcase, Building2, CalendarClock, ChevronLeft, ExternalLink, MapPin, Wallet } from 'lucide-react';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { DirIcon } from '@/components/mw/dir-icon';
import { Illustration } from '@/components/mw/illustration';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { AltchaField } from '@/features/forms/altcha-field';
import { FormRenderer } from '@/features/forms/form-renderer';
import { type FormRuleContext, type FormSchema, asPayload } from '@/features/forms/schema';
import { PlainText } from '@/components/mw/plain-text';
import { formatMinor } from '@/features/hiring/status';
import { useLocale } from '@/hooks/use-locale';
import { formatDate } from '@/lib/format';
import { localized } from '@/lib/localized';
import careers from '@/routes/careers';

type Props = {
    job: App.Domain.Hiring.Data.CareersPostingData;
    open: boolean;
    applyUrl: string | null;
    schema: FormSchema | null;
    context: FormRuleContext;
    privacyNotice: string;
    startedAt: string;
};

/**
 * Job detail and apply (handoff 31, D81): the advert beside the application form on desktop,
 * stacked on phones. The CV goes through the form's file field (dropzone); applying needs the
 * privacy notice accepted and may opt in to the talent pool. Hardened like public forms. A job
 * that takes applications through a Cognito form links to it instead.
 */
export default function Job({ job, open, applyUrl, schema, context, privacyNotice, startedAt }: Props) {
    const { t } = useTranslation('hiring');
    const { locale } = useLocale();
    const page = usePage();
    const flash = page.flash as { applied?: boolean } | undefined;
    const company = localized(page.props.company.name, locale) || job.branch;
    const [altcha, setAltcha] = useState<string | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [sending, setSending] = useState(false);
    const [website, setWebsite] = useState('');
    const [privacy, setPrivacy] = useState(false);
    const [talentPool, setTalentPool] = useState(false);
    const onAltcha = useCallback((payload: string | null) => setAltcha(payload), []);
    const salary = job.salary
        ? [job.salary.min, job.salary.max]
              .filter((v): v is number => v !== null)
              .map((v) => formatMinor(v, job.salary!.currency, locale))
              .join(' – ')
        : null;

    if (flash?.applied) {
        return (
            <div className="mx-auto flex w-full max-w-[640px] flex-col items-center gap-4 px-4 py-12 text-center" data-applied>
                <Head title={`${job.title} · ${company}`} />
                <Illustration name="success-submitted" className="w-56" />
                <h1 className="text-h2">{t('apply.thanks')}</h1>
                <p className="text-body text-text-secondary">{t('apply.received', { job: job.title })}</p>
                <Link href={careers.index.url()} className="inline-flex min-h-11 items-center text-small text-primary hover:underline">
                    {t('apply.moreJobs')}
                </Link>
            </div>
        );
    }

    return (
        <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-8">
            <Head title={`${job.title} · ${company}`} />
            <Link href={careers.index.url()} className="inline-flex min-h-11 items-center gap-1 self-start text-small text-text-secondary hover:text-foreground">
                <DirIcon>
                    <ChevronLeft aria-hidden className="size-4" />
                </DirIcon>
                {t('careers.allJobs')}
            </Link>

            <div className="grid gap-8 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)] lg:items-start">
                <article className="flex flex-col gap-6">
                    <header className="flex flex-col gap-3">
                        <h1 className="text-h1">{job.title}</h1>
                        {job.summary ? <p className="text-body text-text-secondary">{job.summary}</p> : null}
                        <ul className="flex flex-wrap gap-x-5 gap-y-2 text-small text-text-secondary">
                            <li className="inline-flex items-center gap-1.5">
                                <MapPin aria-hidden className="size-4" />
                                {job.location ?? job.branch}
                            </li>
                            <li className="inline-flex items-center gap-1.5">
                                <Briefcase aria-hidden className="size-4" />
                                {[t(`workplace.${job.workplace}`), job.employmentType].filter(Boolean).join(' · ')}
                            </li>
                            {job.department ? (
                                <li className="inline-flex items-center gap-1.5">
                                    <Building2 aria-hidden className="size-4" />
                                    {job.department}
                                </li>
                            ) : null}
                            {salary ? (
                                <li className="inline-flex items-center gap-1.5">
                                    <Wallet aria-hidden className="size-4" />
                                    {t('careers.perMonth', { salary })}
                                </li>
                            ) : null}
                            {job.closesOn ? (
                                <li className="inline-flex items-center gap-1.5">
                                    <CalendarClock aria-hidden className="size-4" />
                                    {t('careers.closes', { date: formatDate(job.closesOn, locale, { dateStyle: 'medium' }) })}
                                </li>
                            ) : null}
                        </ul>
                    </header>
                    <PlainText text={job.description} className="text-body leading-relaxed" />
                </article>

                <section aria-labelledby="apply-title" className="rounded-lg bg-card p-5 shadow-sm sm:p-6 lg:sticky lg:top-6" data-apply>
                    <h2 id="apply-title" className="mb-4 text-h3">
                        {t('apply.title')}
                    </h2>
                    {open && applyUrl ? (
                        <div className="flex flex-col gap-3">
                            <p className="text-small text-text-secondary">{t('apply.external')}</p>
                            <Button asChild className="min-h-11 self-start">
                                <a href={applyUrl} target="_blank" rel="noreferrer" data-apply-external>
                                    {t('apply.externalButton')}
                                    <ExternalLink aria-hidden />
                                    <span className="sr-only">{t('apply.newTab')}</span>
                                </a>
                            </Button>
                        </div>
                    ) : !open || !schema ? (
                        <p className="rounded-md bg-muted p-4 text-small text-text-secondary">{t('apply.closed')}</p>
                    ) : (
                        <>
                            {errors.error ? (
                                <p role="alert" className="mb-4 rounded-md bg-danger-bg p-3 text-small text-danger">
                                    {errors.error}
                                </p>
                            ) : null}
                            <FormRenderer
                                schema={schema}
                                context={context}
                                uploadUrl={careers.uploads.url(job.slug)}
                                serverErrors={errors}
                                submitting={sending}
                                submitLabel={t('apply.submit')}
                                footer={
                                    <div className="flex flex-col gap-3">
                                        {/* Honeypot: hidden from people and assistive tech; bots fill it in. */}
                                        <div aria-hidden className="absolute -start-[9999px] h-px w-px overflow-hidden">
                                            <label htmlFor="website">{t('apply.honeypot')}</label>
                                            <input id="website" tabIndex={-1} autoComplete="off" value={website} onChange={(e) => setWebsite(e.target.value)} />
                                        </div>
                                        {privacyNotice ? <p className="rounded-md bg-muted p-3 text-caption text-text-secondary">{privacyNotice}</p> : null}
                                        <label htmlFor="privacy" className="flex min-h-11 items-start gap-2 text-small">
                                            <Checkbox id="privacy" checked={privacy} onCheckedChange={(on) => setPrivacy(on === true)} className="mt-0.5" aria-describedby={errors.privacy ? 'privacy-error' : undefined} data-privacy />
                                            {t('apply.privacy')}
                                        </label>
                                        {errors.privacy ? (
                                            <p id="privacy-error" role="alert" className="text-caption text-danger">
                                                {errors.privacy}
                                            </p>
                                        ) : null}
                                        <label htmlFor="talent-pool" className="flex min-h-11 items-start gap-2 text-small">
                                            <Checkbox id="talent-pool" checked={talentPool} onCheckedChange={(on) => setTalentPool(on === true)} className="mt-0.5" />
                                            {t('apply.pool')}
                                        </label>
                                        <AltchaField challengeUrl={careers.challenge.url(job.slug)} onChange={onAltcha} />
                                        {errors.altcha ? (
                                            <p role="alert" className="text-caption text-danger">
                                                {errors.altcha}
                                            </p>
                                        ) : null}
                                    </div>
                                }
                                onSubmit={(values) => {
                                    setSending(true);
                                    router.post(careers.apply.url(job.slug), asPayload({ values, altcha, startedAt, website, privacy, talentPool }), {
                                        preserveScroll: true,
                                        onError: setErrors,
                                        onFinish: () => setSending(false),
                                    });
                                }}
                            />
                        </>
                    )}
                </section>
            </div>
        </div>
    );
}
