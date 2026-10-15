import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { DirIcon } from '@/components/mw/dir-icon';
import { PageHeader } from '@/components/mw/page-header';
import { FormRenderer } from '@/features/forms/form-renderer';
import { type FormRuleContext, type FormSchema, asPayload } from '@/features/forms/schema';
import { QuotePanel } from '@/features/leave/quote-panel';
import { RequestTypeIcon } from '@/features/requests/type-icon';
import { useLocale } from '@/hooks/use-locale';
import { localized } from '@/lib/localized';
import requests from '@/routes/requests';

type Props = {
    type: App.Domain.Requests.Data.RequestTypeData;
    schema: FormSchema;
    context: FormRuleContext;
};

/**
 * The request form (handoff 11): the type's published form at 640 px with a summary aside on
 * desktop; one column with the submit button at the end on phones.
 */
export default function CreateRequest({ type, schema, context }: Props) {
    const { t } = useTranslation('requests');
    const { locale } = useLocale();
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [sending, setSending] = useState(false);
    const [values, setValues] = useState<Record<string, unknown>>({});
    const name = localized(type.name, locale);

    return (
        <>
            <Head title={name} />
            <Link
                href={requests.center.url()}
                className="mb-4 inline-flex min-h-11 items-center gap-1 text-small text-text-secondary hover:text-foreground"
            >
                <DirIcon>
                    <ChevronLeft aria-hidden className="size-4" />
                </DirIcon>
                {t('create.back')}
            </Link>
            <PageHeader
                title={name}
                description={localized(type.description, locale) || undefined}
            />

            <div className="flex flex-col gap-6 lg:flex-row lg:items-start">
                <section
                    aria-label={t('create.formLabel')}
                    className="w-full max-w-[640px] rounded-lg bg-card p-5 shadow-sm sm:p-6"
                >
                    {errors.error ? (
                        <p
                            role="alert"
                            className="mb-4 rounded-md bg-danger-bg p-3 text-small text-danger"
                        >
                            {errors.error}
                        </p>
                    ) : null}
                    <FormRenderer
                        schema={schema}
                        context={context}
                        uploadUrl={requests.uploads.url(type.id)}
                        serverErrors={errors}
                        submitting={sending}
                        submitLabel={t('create.submit')}
                        onValuesChange={type.behaviour ? setValues : undefined}
                        onSubmit={(values) => {
                            setSending(true);
                            router.post(requests.store.url(type.id), asPayload({ values }), {
                                onError: setErrors,
                                onFinish: () => setSending(false),
                            });
                        }}
                    />
                </section>

                <aside className="flex w-full flex-col gap-4 rounded-lg border border-border bg-card p-5 lg:sticky lg:top-24 lg:w-80">
                    <span className="flex size-10 items-center justify-center rounded-md bg-primary/10 text-primary">
                        <RequestTypeIcon icon={type.icon} />
                    </span>
                    <div className="flex flex-col gap-1">
                        <h2 className="text-h4">{t('create.nextTitle')}</h2>
                        <p className="text-small text-text-secondary">{t('create.nextBody')}</p>
                    </div>
                    {type.behaviour ? <QuotePanel typeId={type.id} values={values} /> : null}
                    {type.isSensitive ? (
                        <p className="flex items-start gap-2 rounded-md bg-muted p-3 text-small text-text-secondary">
                            <ShieldCheck aria-hidden className="mt-0.5 size-4 shrink-0" />
                            {t('create.sensitive')}
                        </p>
                    ) : null}
                </aside>
            </div>
        </>
    );
}
