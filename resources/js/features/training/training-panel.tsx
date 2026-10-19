import { Link, router } from '@inertiajs/react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Section } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Textarea } from '@/components/ui/textarea';
import { useLocale } from '@/hooks/use-locale';
import { api } from '@/lib/api';
import { formatDate } from '@/lib/format';
import training from '@/routes/training';
import { ProgressRing } from './progress-ring';
import { CertificateList, ExternalList } from './records';
import { DueChip, EnrollmentStatusBadge } from './status';

type Enrollment = App.Domain.Training.Data.EnrollmentData;
type Certificate = App.Domain.Training.Data.CertificateData;
type TrainingRecord = {
    data: {
        enrollments: Enrollment[];
        certificates: Certificate[];
        external: App.Domain.Training.Data.ExternalTrainingData[];
    };
    meta: { canManage: boolean; courses: App.Domain.Training.Data.CourseRefData[] };
};

/**
 * Someone's training record on their profile (handoff 8 Training tab): courses and progress,
 * certificates (D103) and outside training (D105). Trainers and HR over the person can revoke a
 * certificate, add one for outside training, or meet a request with a catalog course.
 */
export function TrainingPanel({ employeeId }: { employeeId: string }) {
    const { t } = useTranslation('training');
    const { locale } = useLocale();
    const client = useQueryClient();
    const [revoking, setRevoking] = useState<Certificate | null>(null);
    const record = useQuery({
        queryKey: ['training-record', employeeId],
        queryFn: ({ signal }) =>
            api<TrainingRecord>(`employees/${employeeId}/training`, { signal }),
    });
    const refresh = () =>
        void client.invalidateQueries({ queryKey: ['training-record', employeeId] });

    if (record.isPending) {
        return (
            <div className="flex flex-col gap-2" aria-busy="true">
                <Skeleton className="h-16 w-full" />
                <Skeleton className="h-16 w-full" />
            </div>
        );
    }

    if (record.isError) {
        return <PageState kind="error" title={t('panel.error')} />;
    }

    const { enrollments, certificates, external } = record.data.data;
    const { canManage, courses } = record.data.meta;

    if (enrollments.length === 0 && certificates.length === 0 && external.length === 0) {
        return (
            <PageState
                kind="empty"
                illustration="empty-trainings"
                title={t('panel.empty')}
                description={t('panel.emptyHelp')}
            />
        );
    }

    return (
        <div className="flex flex-col gap-6">
            {enrollments.length > 0 ? (
                <Section title={t('panel.courses')}>
                    <ul
                        className="flex flex-col divide-y divide-border rounded-lg border border-border bg-card"
                        data-training-record
                    >
                        {enrollments.map((row) => (
                            <li key={row.id} className="flex items-center gap-3 p-3">
                                <ProgressRing
                                    value={row.progress}
                                    size={44}
                                    label={t('catalog.progress', { value: row.progress })}
                                />
                                <div className="flex min-w-0 flex-1 flex-col gap-1">
                                    <Link
                                        href={training.courses.show.url(row.course.id)}
                                        className="truncate text-body font-medium hover:underline"
                                    >
                                        {row.course.title}
                                    </Link>
                                    <div className="flex flex-wrap items-center gap-2 text-caption text-text-secondary">
                                        <EnrollmentStatusBadge status={row.status} />
                                        <span>{t(`source.${row.source}`)}</span>
                                        {row.dueOn && row.status === 'enrolled' ? (
                                            <DueChip
                                                dueOn={row.dueOn}
                                                overdue={row.overdue}
                                                label={
                                                    row.overdue
                                                        ? t('course.overdueOn', {
                                                              date: formatDate(row.dueOn, locale, {
                                                                  dateStyle: 'medium',
                                                              }),
                                                          })
                                                        : t('course.due', {
                                                              date: formatDate(row.dueOn, locale, {
                                                                  dateStyle: 'medium',
                                                              }),
                                                          })
                                                }
                                            />
                                        ) : null}
                                        {row.completedAt ? (
                                            <span>
                                                {t('course.completedOn', {
                                                    date: formatDate(row.completedAt, locale, {
                                                        dateStyle: 'medium',
                                                    }),
                                                })}
                                            </span>
                                        ) : null}
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ul>
                </Section>
            ) : null}
            {certificates.length > 0 ? (
                <Section title={t('my.groups.certificates')}>
                    <CertificateList
                        certificates={certificates}
                        onRevoke={canManage ? setRevoking : undefined}
                    />
                </Section>
            ) : null}
            {external.length > 0 ? (
                <Section title={t('my.groups.external')}>
                    <ExternalList
                        records={external}
                        canUpload={canManage}
                        courses={canManage ? courses : undefined}
                        onUploaded={refresh}
                    />
                </Section>
            ) : null}
            {revoking ? (
                <RevokeDialog
                    certificate={revoking}
                    onClose={() => setRevoking(null)}
                    onDone={refresh}
                />
            ) : null}
        </div>
    );
}

function RevokeDialog({
    certificate,
    onClose,
    onDone,
}: {
    certificate: Certificate;
    onClose: () => void;
    onDone: () => void;
}) {
    const { t } = useTranslation('training');
    const [reason, setReason] = useState('');
    const [error, setError] = useState<string | null>(null);

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={t('certificate.revokeTitle')}
            description={t('certificate.revokeHelp', { serial: certificate.serial })}
            footer={
                <div className="flex justify-end gap-2">
                    <Button variant="ghost" className="min-h-11" onClick={onClose}>
                        {t('cancel')}
                    </Button>
                    <Button
                        variant="destructive"
                        className="min-h-11"
                        disabled={reason.trim() === ''}
                        data-confirm-revoke
                        onClick={() =>
                            router.post(
                                training.certificates.revoke.url(certificate.id),
                                { reason },
                                {
                                    preserveScroll: true,
                                    onError: (e) => setError(e.reason ?? e.error ?? null),
                                    onSuccess: () => {
                                        onDone();
                                        onClose();
                                    },
                                },
                            )
                        }
                    >
                        {t('certificate.revoke')}
                    </Button>
                </div>
            }
        >
            <label className="flex flex-col gap-1 text-small">
                {t('certificate.reason')}
                <Textarea
                    value={reason}
                    onChange={(e) => setReason(e.target.value)}
                    rows={3}
                    maxLength={500}
                />
            </label>
            {error ? (
                <p role="alert" className="mt-2 text-caption text-danger">
                    {error}
                </p>
            ) : null}
        </ResponsiveDialog>
    );
}
