import { Head, router, usePage } from '@inertiajs/react';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Illustration } from '@/components/mw/illustration';
import { PageState } from '@/components/mw/page-state';
import { AltchaField } from '@/features/forms/altcha-field';
import { FormRenderer } from '@/features/forms/form-renderer';
import { type FormRuleContext, type FormSchema, asPayload } from '@/features/forms/schema';
import { useLocale } from '@/hooks/use-locale';
import { localized } from '@/lib/localized';
import publicForms from '@/routes/public-forms';

type Props = {
    form: { slug: string; name: Record<string, string>; description: Record<string, string>; successMessage: Record<string, string> };
    schema: FormSchema | null;
    closed: boolean;
    context: FormRuleContext;
    startedAt: string;
};

/**
 * A public form (D53): no sign-in. ALTCHA runs in the browser, a hidden honeypot catches bots,
 * and the server enforces a minimum fill time and rate limits.
 */
export default function PublicForm({ form, schema, closed, context, startedAt }: Props) {
    const { t } = useTranslation('forms');
    const { locale } = useLocale();
    const { flash } = usePage();
    const [altcha, setAltcha] = useState<string | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [sending, setSending] = useState(false);
    const [website, setWebsite] = useState('');
    const onAltcha = useCallback((payload: string | null) => setAltcha(payload), []);
    const name = localized(form.name, locale);

    if ((flash as { submitted?: boolean } | undefined)?.submitted) {
        return (
            <div className="mx-auto flex w-full max-w-[640px] flex-col items-center gap-4 px-4 py-12 text-center">
                <Head title={name} />
                <Illustration name="success-submitted" className="w-56" />
                <h1 className="text-h2">{t('public.thanks')}</h1>
                <p className="text-body text-text-secondary">{localized(form.successMessage, locale) || t('public.received')}</p>
            </div>
        );
    }

    return (
        <div className="mx-auto flex w-full max-w-[640px] flex-col gap-6 px-4 py-8">
            <Head title={name} />
            <header className="flex flex-col gap-1">
                <h1 className="text-h2">{name}</h1>
                {localized(form.description, locale) ? <p className="text-body text-text-secondary">{localized(form.description, locale)}</p> : null}
            </header>
            {closed || !schema ? (
                <PageState kind="empty" illustration="empty-requests" title={t('public.closed')} />
            ) : (
                <div className="rounded-lg bg-card p-5 shadow-sm sm:p-6">
                    {errors.error ? (
                        <p role="alert" className="mb-4 rounded-md bg-danger-bg p-3 text-small text-danger">
                            {errors.error}
                        </p>
                    ) : null}
                    <FormRenderer
                        schema={schema}
                        context={context}
                        uploadUrl={publicForms.uploads.url(form.slug)}
                        serverErrors={errors}
                        submitting={sending}
                        submitLabel={t('public.send')}
                        footer={
                            <div className="flex flex-col gap-2">
                                {/* Honeypot: hidden from people and assistive tech; bots fill it in. */}
                                <div aria-hidden className="absolute -start-[9999px] h-px w-px overflow-hidden">
                                    <label htmlFor="website">{t('public.honeypot')}</label>
                                    <input id="website" tabIndex={-1} autoComplete="off" value={website} onChange={(event) => setWebsite(event.target.value)} />
                                </div>
                                <AltchaField challengeUrl={publicForms.challenge.url(form.slug)} onChange={onAltcha} />
                                {errors.altcha ? (
                                    <p role="alert" className="text-caption text-danger">
                                        {errors.altcha}
                                    </p>
                                ) : null}
                            </div>
                        }
                        onSubmit={(values) => {
                            setSending(true);
                            router.post(publicForms.submit.url(form.slug), asPayload({ values, altcha, startedAt, website }), {
                                preserveScroll: true,
                                onError: setErrors,
                                onFinish: () => setSending(false),
                            });
                        }}
                    />
                </div>
            )}
        </div>
    );
}
