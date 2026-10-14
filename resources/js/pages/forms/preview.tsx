import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { DirIcon } from '@/components/mw/dir-icon';
import { FormRenderer } from '@/features/forms/form-renderer';
import { type FormRuleContext, type FormSchema, asPayload } from '@/features/forms/schema';
import { useLocale } from '@/hooks/use-locale';
import { formatNumber } from '@/lib/format';
import { localized } from '@/lib/localized';
import forms from '@/routes/forms';

type Props = {
    form: App.Domain.Forms.Data.FormData;
    version: { id: string; number: number; status: string };
    schema: FormSchema;
    context: FormRuleContext;
};

/**
 * Fill in a form as a requester would (handoff 11's form body). Sending records a test
 * submission: validated like a real one, marked "test" in the list.
 */
export default function FormPreview({ form, version, schema, context }: Props) {
    const { t } = useTranslation('forms');
    const { locale } = useLocale();
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [sending, setSending] = useState(false);
    const [key, setKey] = useState(0);
    const query = version.status === 'draft' ? { version: 'draft' } : {};

    return (
        <>
            <Head title={t('preview.pageTitle', { name: localized(form.name, locale) })} />
            <Link
                href={forms.builder.url(form.id)}
                className="mb-4 inline-flex items-center gap-1 text-small text-text-secondary hover:text-foreground"
            >
                <DirIcon>
                    <ChevronLeft aria-hidden className="size-4" />
                </DirIcon>
                {t('preview.back')}
            </Link>
            <div className="mx-auto flex w-full max-w-[640px] flex-col gap-6">
                <header className="flex flex-col gap-1">
                    <h1 className="text-h2">{localized(form.name, locale)}</h1>
                    {localized(form.description, locale) ? (
                        <p className="text-body text-text-secondary">
                            {localized(form.description, locale)}
                        </p>
                    ) : null}
                    <p className="text-caption text-muted-foreground">
                        {t('preview.testNote', { version: formatNumber(version.number, locale) })}
                    </p>
                </header>
                {errors.error ? (
                    <p role="alert" className="rounded-md bg-danger-bg p-3 text-small text-danger">
                        {errors.error}
                    </p>
                ) : null}
                <div className="rounded-lg bg-card p-5 shadow-sm sm:p-6">
                    <FormRenderer
                        key={key}
                        schema={schema}
                        context={context}
                        uploadUrl={forms.uploads.url(form.id, { query })}
                        serverErrors={errors}
                        submitting={sending}
                        submitLabel={t('preview.send')}
                        onSubmit={(values) => {
                            setSending(true);
                            router.post(forms.test.url(form.id, { query }), asPayload({ values }), {
                                preserveScroll: true,
                                onSuccess: () => {
                                    setErrors({});
                                    setKey((current) => current + 1);
                                },
                                onError: setErrors,
                                onFinish: () => setSending(false),
                            });
                        }}
                    />
                </div>
            </div>
        </>
    );
}
