import { Head, Link, useForm } from '@inertiajs/react';
import { Download, FileSpreadsheet, UploadCloud } from 'lucide-react';
import { type DragEvent, type FormEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/mw/page-header';
import { Button } from '@/components/ui/button';
import { DesktopOnly, ImportStepper } from '@/features/people/imports/import-stepper';
import { useLocale } from '@/hooks/use-locale';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import people from '@/routes/people';

type Import = App.Domain.People.Data.ImportData;

/** Import wizard, step 1 (handoff 9): drop an XLSX/CSV, or start from the template. */
export default function ImportCreate({ recent }: { recent: Import[] }) {
    const { t } = useTranslation('imports');
    const { locale } = useLocale();
    const [dragging, setDragging] = useState(false);
    const form = useForm<{ file: File | null }>({ file: null });

    const pick = (file: File | undefined) => file && form.setData('file', file);

    const drop = (event: DragEvent) => {
        event.preventDefault();
        setDragging(false);
        pick(event.dataTransfer.files[0]);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(people.imports.store.url(), { forceFormData: true });
    };

    return (
        <>
            <Head title={t('pageTitle')} />
            <PageHeader title={t('title')} description={t('description')} />
            <DesktopOnly />

            <div className="hidden md:block">
                <ImportStepper current="upload" />
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <label
                        htmlFor="import-file"
                        onDragOver={(event) => {
                            event.preventDefault();
                            setDragging(true);
                        }}
                        onDragLeave={() => setDragging(false)}
                        onDrop={drop}
                        className={cn(
                            'flex cursor-pointer flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed border-border bg-card p-10 text-center transition-colors duration-150 ease-soft hover:border-primary',
                            dragging && 'border-primary bg-primary/5',
                        )}
                    >
                        {form.data.file ? (
                            <FileSpreadsheet aria-hidden className="size-10 text-primary" />
                        ) : (
                            <UploadCloud aria-hidden className="size-10 text-text-secondary" />
                        )}
                        <span className="text-body font-semibold text-foreground">
                            {form.data.file ? <bdi>{form.data.file.name}</bdi> : t('upload.drop')}
                        </span>
                        <span className="text-small text-muted-foreground">{t('upload.hint')}</span>
                        <input
                            id="import-file"
                            type="file"
                            accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv"
                            className="sr-only"
                            onChange={(event) => pick(event.target.files?.[0])}
                        />
                    </label>
                    <InputError
                        message={form.errors.file ?? (form.errors as Record<string, string>).error}
                    />
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <Button variant="link" asChild className="px-0">
                            <a href={people.imports.template.url()}>
                                <Download aria-hidden />
                                {t('upload.template')}
                            </a>
                        </Button>
                        <Button type="submit" disabled={!form.data.file} loading={form.processing}>
                            {t('upload.submit')}
                        </Button>
                    </div>
                    {form.progress ? (
                        <progress value={form.progress.percentage} max={100} className="w-full" />
                    ) : null}
                </form>

                {recent.length ? (
                    <section className="mt-10">
                        <h2 className="mb-3 text-body font-semibold text-foreground">
                            {t('recent')}
                        </h2>
                        <ul className="flex flex-col overflow-hidden rounded-lg border border-border bg-card">
                            {recent.map((item) => (
                                <li
                                    key={item.id}
                                    className="border-b border-border last:border-b-0"
                                >
                                    <Link
                                        href={people.imports.show.url(item.id)}
                                        className="flex items-center gap-3 px-4 py-3 hover:bg-muted"
                                    >
                                        <FileSpreadsheet
                                            aria-hidden
                                            className="size-5 text-text-secondary"
                                        />
                                        <bdi className="flex-1 truncate text-sm">
                                            {item.fileName}
                                        </bdi>
                                        <span className="text-caption text-muted-foreground">
                                            {t(`statuses.${item.status}`)}
                                        </span>
                                        <span className="text-caption text-muted-foreground">
                                            {formatDate(item.createdAt, locale, {
                                                dateStyle: 'medium',
                                            })}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </section>
                ) : null}
            </div>
        </>
    );
}
