import { Head, Link, useForm } from '@inertiajs/react';
import { CircleAlert, Plus } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { BilingualInput } from '@/components/mw/bilingual-input';
import { Field } from '@/components/mw/field';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useLocale } from '@/hooks/use-locale';
import { formatNumber } from '@/lib/format';
import { localized } from '@/lib/localized';
import workflows from '@/routes/workflows';

type Workflow = App.Domain.Workflows.Data.WorkflowDefinitionData;

/** Workflows (ADR-0009): every process with its live version, pending changes and use. */
export default function WorkflowsIndex({
    workflows: list,
    can,
}: {
    workflows: Workflow[];
    can: { create: boolean };
}) {
    const { t } = useTranslation('workflows');
    const { locale } = useLocale();
    const [creating, setCreating] = useState(false);

    return (
        <>
            <Head title={t('index.pageTitle')} />
            <PageHeader
                title={t('index.title')}
                description={t('index.description')}
                actions={
                    <>
                        <Button variant="ghost" asChild>
                            <Link href={workflows.stuck.index.url()}>
                                <CircleAlert aria-hidden />
                                {t('index.stuck')}
                            </Link>
                        </Button>
                        {can.create ? (
                            <Button onClick={() => setCreating(true)}>
                                <Plus aria-hidden />
                                {t('index.new')}
                            </Button>
                        ) : null}
                    </>
                }
            />

            {list.length === 0 ? (
                <PageState kind="empty" illustration="empty-requests" title={t('index.empty')} />
            ) : (
                <ul className="flex flex-col gap-2 md:gap-0 md:overflow-hidden md:rounded-lg md:border md:border-border md:bg-card">
                    {list.map((workflow) => (
                        <li
                            key={workflow.id}
                            className="relative flex flex-col gap-2 rounded-lg bg-card p-4 shadow-sm hover:bg-muted/60 md:flex-row md:items-center md:gap-4 md:rounded-none md:border-b md:border-border md:px-4 md:py-3 md:shadow-none md:last:border-b-0"
                        >
                            <span className="flex min-w-0 flex-1 flex-col">
                                <Link
                                    href={workflows.builder.url(workflow.id)}
                                    className="truncate text-sm font-medium after:absolute after:inset-0 focus-visible:outline-none"
                                >
                                    {localized(workflow.name, locale)}
                                </Link>
                                <span className="truncate text-caption text-muted-foreground">
                                    <bdi dir="ltr">{workflow.code}</bdi>
                                    {workflow.requestTypes.length > 0
                                        ? ` · ${workflow.requestTypes.map((type) => localized(type, locale)).join('، ')}`
                                        : ''}
                                </span>
                            </span>
                            <span className="flex flex-wrap items-center gap-2 md:w-64">
                                {workflow.publishedVersion !== null ? (
                                    <Badge variant="outline">
                                        {t('index.live', {
                                            number: formatNumber(workflow.publishedVersion, locale),
                                        })}
                                    </Badge>
                                ) : (
                                    <Badge variant="outline">{t('index.notPublished')}</Badge>
                                )}
                                {workflow.hasDraft ? (
                                    <Badge variant="secondary">{t('index.changes')}</Badge>
                                ) : null}
                            </span>
                            <span className="text-small text-text-secondary tabular-nums md:w-40">
                                {t('index.running', {
                                    count: workflow.openInstances,
                                    formatted: formatNumber(workflow.openInstances, locale),
                                })}
                            </span>
                        </li>
                    ))}
                </ul>
            )}

            {creating ? <CreateDialog onClose={() => setCreating(false)} /> : null}
        </>
    );
}

function CreateDialog({ onClose }: { onClose: () => void }) {
    const { t } = useTranslation('workflows');
    const form = useForm({ code: '', name: { en: '', ar: '' } });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(workflows.store.url());
    };

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={t('index.new')}
            description={t('index.newHelp')}
        >
            <form onSubmit={submit} className="flex flex-col gap-4">
                <Field id="workflow-code" label={t('index.code')} error={form.errors.code} required>
                    <Input
                        id="workflow-code"
                        dir="ltr"
                        value={form.data.code}
                        onChange={(event) => form.setData('code', event.target.value)}
                        required
                    />
                </Field>
                <BilingualInput
                    id="workflow-name"
                    label={t('index.name')}
                    value={form.data.name}
                    onChange={(name) => form.setData('name', name)}
                    errors={form.errors}
                    errorKey="name"
                />
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="outline" onClick={onClose}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="submit" loading={form.processing}>
                        {t('index.create')}
                    </Button>
                </div>
            </form>
        </ResponsiveDialog>
    );
}
