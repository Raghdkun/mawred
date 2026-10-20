import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, Save } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { DirIcon } from '@/components/mw/dir-icon';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { BuilderStepper } from '@/features/reports/builder/builder-stepper';
import {
    type Definition,
    EMPTY,
    fromSaved,
    STEPS,
    type Step,
    stepOf,
} from '@/features/reports/builder/definition';
import {
    DatasetStep,
    FieldsStep,
    FiltersStep,
    GroupStep,
    VisualizeStep,
} from '@/features/reports/builder/steps';
import { useReportPreview } from '@/features/reports/builder/use-report-preview';
import { KpiCards } from '@/features/reports/kpi-cards';
import { ReportChart } from '@/features/reports/report-chart';
import { ReportTable } from '@/features/reports/report-table';
import reports from '@/routes/reports';

type Props = {
    datasets: App.Domain.Reports.Data.DatasetData[];
    saved: App.Domain.Reports.Data.SavedReportData | null;
};

/**
 * The custom report builder (handoff 37, D118): Dataset → Fields → Filters → Group → Visualize
 * with a live preview beside it. Desktop only (1280+); smaller screens get a note.
 */
export default function ReportBuilderPage(props: Props) {
    return <ReportBuilder key={props.saved?.id ?? 'new'} {...props} />;
}

function ReportBuilder({ datasets, saved }: Props) {
    const { t } = useTranslation('reports');
    const [definition, setDefinition] = useState<Definition>(() =>
        saved ? fromSaved(saved.definition) : EMPTY,
    );
    const [step, setStep] = useState<Step>(saved ? 'visualize' : 'dataset');
    const [name, setName] = useState(saved?.name ?? '');
    const [description, setDescription] = useState(saved?.description ?? '');
    const [saveErrors, setSaveErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);
    const preview = useReportPreview(definition);
    const dataset = datasets.find((d) => d.key === definition.dataset) ?? null;
    const update = (patch: Partial<Definition>) =>
        setDefinition((current) => ({ ...current, ...patch }));
    const flagged = new Set<Step>(
        [...Object.keys(preview.errors), ...Object.keys(saveErrors)]
            .filter((key) => key !== 'error')
            .map(stepOf),
    );
    const index = STEPS.indexOf(step);
    const savedId = saved?.id ?? null;

    const chooseDataset = (key: string) => {
        if (key !== definition.dataset) {
            setDefinition({ ...EMPTY, dataset: key });
        }

        setStep('fields');
    };

    const save = () => {
        setSaving(true);
        const payload = { name, description: description || null, definition };
        const options = {
            onError: (errors: Record<string, string>) => {
                setSaveErrors(errors);
                const first = Object.keys(errors).find((key) => key !== 'error');

                if (first) {
                    setStep(stepOf(first));
                }
            },
            onFinish: () => setSaving(false),
        };

        if (savedId) {
            router.put(reports.saved.update.url(savedId), payload, options);
        } else {
            router.post(reports.saved.store.url(), payload, options);
        }
    };

    const fieldErrors = Object.fromEntries(
        Object.entries(saveErrors).map(([key, message]) => [key, [message]]),
    );
    const errors = { ...fieldErrors, ...preview.errors };

    return (
        <>
            <Head
                title={saved ? t('builder.editTitle', { name: saved.name }) : t('builder.newTitle')}
            />
            <Link
                href={saved ? reports.show.url(saved.key) : reports.index.url()}
                className="mb-4 inline-flex min-h-11 items-center gap-1 text-small text-text-secondary hover:text-foreground"
            >
                <DirIcon>
                    <ChevronLeft aria-hidden className="size-4" />
                </DirIcon>
                {saved ? saved.name : t('back')}
            </Link>
            <PageHeader
                title={saved ? t('builder.editTitle', { name: saved.name }) : t('builder.newTitle')}
                description={t('builder.description')}
            />
            <div className="xl:hidden">
                <PageState
                    kind="empty"
                    illustration="empty-reports"
                    title={t('builder.desktopOnly.title')}
                    description={t('builder.desktopOnly.body')}
                />
            </div>
            <div className="hidden flex-col gap-6 xl:flex" data-builder>
                <BuilderStepper
                    current={step}
                    onSelect={setStep}
                    reachable={dataset !== null}
                    flagged={flagged}
                />
                {saveErrors.error ? (
                    <p role="alert" className="rounded-md bg-danger-bg p-3 text-small text-danger">
                        {saveErrors.error}
                    </p>
                ) : null}
                <div className="grid grid-cols-[minmax(0,5fr)_minmax(0,7fr)] items-start gap-6">
                    <div className="flex flex-col gap-6 rounded-lg border border-border bg-card p-5">
                        {step === 'dataset' || !dataset ? (
                            <DatasetStep
                                datasets={datasets}
                                value={definition.dataset}
                                onChange={chooseDataset}
                                error={errors.dataset?.[0]}
                            />
                        ) : step === 'fields' ? (
                            <FieldsStep
                                dataset={dataset}
                                definition={definition}
                                update={update}
                                error={errors.columns?.[0]}
                            />
                        ) : step === 'filters' ? (
                            <FiltersStep
                                dataset={dataset}
                                definition={definition}
                                update={update}
                                errors={errors}
                            />
                        ) : step === 'group' ? (
                            <GroupStep
                                dataset={dataset}
                                definition={definition}
                                update={update}
                                errors={errors}
                            />
                        ) : (
                            <VisualizeStep
                                dataset={dataset}
                                definition={definition}
                                update={update}
                                name={name}
                                onName={setName}
                                description={description}
                                onDescription={setDescription}
                                errors={saveErrors}
                            />
                        )}
                        <div className="flex justify-between gap-2 border-t border-border pt-4">
                            <Button
                                variant="outline"
                                className="min-h-11"
                                disabled={index === 0}
                                onClick={() => setStep(STEPS[index - 1] ?? 'dataset')}
                            >
                                {t('builder.back')}
                            </Button>
                            {step === 'visualize' ? (
                                <Button
                                    className="min-h-11"
                                    onClick={save}
                                    disabled={saving || name.trim() === '' || !preview.enabled}
                                    data-save-report
                                >
                                    <Save aria-hidden />
                                    {saving ? t('builder.saving') : t('builder.save')}
                                </Button>
                            ) : (
                                <Button
                                    className="min-h-11"
                                    disabled={!dataset}
                                    onClick={() => setStep(STEPS[index + 1] ?? 'visualize')}
                                    data-next-step
                                >
                                    {t('builder.next')}
                                </Button>
                            )}
                        </div>
                    </div>
                    <section
                        aria-labelledby="preview-title"
                        aria-busy={preview.loading}
                        className="flex min-w-0 flex-col gap-4"
                        data-builder-preview
                    >
                        <h2 id="preview-title" className="text-h4">
                            {t('builder.preview.title')}
                        </h2>
                        {!preview.enabled ? (
                            <p className="rounded-lg border border-dashed border-border p-6 text-center text-small text-text-secondary">
                                {t('builder.preview.empty')}
                            </p>
                        ) : preview.failed && !preview.result ? (
                            <p
                                role="alert"
                                className="rounded-md bg-danger-bg p-3 text-small text-danger"
                            >
                                {t('builder.preview.error')}
                            </p>
                        ) : !preview.result ? (
                            <div
                                className="flex flex-col gap-4"
                                role="status"
                                aria-label={t('result.loading')}
                            >
                                <Skeleton className="h-20 rounded-lg" />
                                <Skeleton className="h-64 rounded-lg" />
                            </div>
                        ) : (
                            <div
                                className={
                                    preview.loading
                                        ? 'opacity-60 transition-opacity motion-reduce:transition-none'
                                        : undefined
                                }
                                aria-live="polite"
                            >
                                <div className="flex flex-col gap-4">
                                    {preview.failed ? (
                                        <p
                                            role="alert"
                                            className="rounded-md bg-danger-bg p-3 text-small text-danger"
                                        >
                                            {t('builder.preview.error')}
                                        </p>
                                    ) : null}
                                    <KpiCards kpis={preview.result.kpis} />
                                    {preview.result.chart &&
                                    preview.result.chart.data.length > 0 ? (
                                        <ReportChart
                                            chart={preview.result.chart}
                                            title={name || t('builder.newTitle')}
                                        />
                                    ) : null}
                                    {preview.result.total > 0 ? (
                                        <ReportTable
                                            result={preview.result}
                                            title={name || t('builder.newTitle')}
                                        />
                                    ) : (
                                        <PageState
                                            kind="no-results"
                                            illustration="empty-reports"
                                            title={t('result.empty')}
                                            description={t('result.emptyHelp')}
                                        />
                                    )}
                                </div>
                            </div>
                        )}
                    </section>
                </div>
            </div>
        </>
    );
}
