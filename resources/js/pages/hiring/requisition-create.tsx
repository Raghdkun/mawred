import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { DirIcon } from '@/components/mw/dir-icon';
import { PageHeader } from '@/components/mw/page-header';
import { FormRenderer } from '@/features/forms/form-renderer';
import { type FormRuleContext, type FormSchema, asPayload } from '@/features/forms/schema';
import hiring from '@/routes/hiring';
import requests from '@/routes/requests';

type Props = {
    type: App.Domain.Requests.Data.RequestTypeData;
    schema: FormSchema;
    context: FormRuleContext;
};

/**
 * Raising a requisition (D84): the Job requisition request type's form (HR can reshape it in
 * the form builder); sending it starts the approval chain.
 */
export default function RequisitionCreate({ type, schema, context }: Props) {
    const { t } = useTranslation('hiring');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [sending, setSending] = useState(false);

    return (
        <>
            <Head title={t('raise.title')} />
            <Link
                href={hiring.requisitions.index.url()}
                className="mb-4 inline-flex min-h-11 items-center gap-1 text-small text-text-secondary hover:text-foreground"
            >
                <DirIcon>
                    <ChevronLeft aria-hidden className="size-4" />
                </DirIcon>
                {t('raise.back')}
            </Link>
            <PageHeader title={t('raise.title')} description={t('raise.description')} />

            <div className="flex flex-col gap-6 lg:flex-row lg:items-start">
                <section
                    aria-label={t('raise.title')}
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
                        submitLabel={t('raise.submit')}
                        onSubmit={(values) => {
                            setSending(true);
                            router.post(hiring.requisitions.store.url(), asPayload({ values }), {
                                onError: setErrors,
                                onFinish: () => setSending(false),
                            });
                        }}
                    />
                </section>
                <aside className="flex w-full flex-col gap-2 rounded-lg border border-border bg-card p-5 lg:sticky lg:top-24 lg:w-80">
                    <h2 className="text-h4">{t('raise.nextTitle')}</h2>
                    <p className="text-small text-text-secondary">{t('raise.nextBody')}</p>
                </aside>
            </div>
        </>
    );
}
