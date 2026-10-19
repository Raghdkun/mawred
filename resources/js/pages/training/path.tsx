import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { DirIcon } from '@/components/mw/dir-icon';
import { PlainText } from '@/components/mw/plain-text';
import { Button } from '@/components/ui/button';
import { PathStepper } from '@/features/training/path-stepper';
import training from '@/routes/training';

type Props = {
    path: App.Domain.Training.Data.LearningPathData;
    can: { join: boolean };
};

/** A learning path (handoff 33): its courses as a stepper, and joining it. */
export default function Path({ path, can }: Props) {
    const { t } = useTranslation('training');
    const [busy, setBusy] = useState(false);
    const done = path.courses.filter((c) => c.status === 'completed').length;

    return (
        <>
            <Head title={path.title} />
            <Link
                href={training.catalog.url({ query: { view: 'paths' } })}
                className="mb-4 inline-flex min-h-11 items-center gap-1 text-small text-text-secondary hover:text-foreground"
            >
                <DirIcon>
                    <ChevronLeft aria-hidden className="size-4" />
                </DirIcon>
                {t('path.back')}
            </Link>
            <div className="flex max-w-3xl flex-col gap-6">
                <header className="flex flex-col gap-2">
                    <h1 className="text-h2">{path.title}</h1>
                    {path.description ? (
                        <PlainText
                            text={path.description}
                            className="text-body text-text-secondary"
                        />
                    ) : null}
                    <p className="text-small text-text-secondary">
                        {t('path.progress', { done, total: path.courses.length })}
                    </p>
                </header>
                {can.join && !path.joined ? (
                    <Button
                        className="min-h-11 self-start"
                        disabled={busy}
                        data-join-path
                        onClick={() => {
                            setBusy(true);
                            router.post(
                                training.paths.join.url(path.id),
                                {},
                                { preserveScroll: true, onFinish: () => setBusy(false) },
                            );
                        }}
                    >
                        {t('path.join')}
                    </Button>
                ) : null}
                <section className="rounded-lg bg-card p-5 shadow-sm">
                    <PathStepper courses={path.courses} />
                </section>
            </div>
        </>
    );
}
