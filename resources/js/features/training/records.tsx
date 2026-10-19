import { router } from '@inertiajs/react';
import { Award, Download, ExternalLink, FileUp } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { useLocale } from '@/hooks/use-locale';
import { minorDigits } from '@/features/forms/upload';
import { formatDate, formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import training from '@/routes/training';

type Certificate = App.Domain.Training.Data.CertificateData;
type External = App.Domain.Training.Data.ExternalTrainingData;

const STATE: Record<string, string> = {
    valid: 'border-status-approved-border bg-status-approved-bg text-status-approved-fg',
    expired: 'border-status-cancelled-border bg-status-cancelled-bg text-status-cancelled-fg',
    revoked: 'border-status-rejected-border bg-status-rejected-bg text-status-rejected-fg',
};

/** Certificates on someone's record (D103): course, dates, state, the PDF and the public check. */
export function CertificateList({
    certificates,
    onRevoke,
}: {
    certificates: Certificate[];
    onRevoke?: (certificate: Certificate) => void;
}) {
    const { t } = useTranslation('training');
    const { locale } = useLocale();
    const date = (value: string) => formatDate(value, locale, { dateStyle: 'medium' });

    return (
        <ul
            className="flex flex-col divide-y divide-border rounded-lg border border-border bg-card"
            data-certificates
        >
            {certificates.map((c) => (
                <li key={c.id} className="flex flex-wrap items-center gap-3 p-3">
                    <Award aria-hidden className="size-6 shrink-0 text-primary" />
                    <div className="flex min-w-0 flex-1 flex-col gap-1">
                        <span className="text-body font-medium">{c.course}</span>
                        <span className="flex flex-wrap items-center gap-2 text-caption text-text-secondary">
                            <span className={cn('rounded-full border px-2 py-0.5', STATE[c.state])}>
                                {t(`certificate.state.${c.state}`)}
                            </span>
                            <span>{t('certificate.issued', { date: date(c.issuedOn) })}</span>
                            <span>
                                {c.expiresOn
                                    ? t('certificate.expires', { date: date(c.expiresOn) })
                                    : t('certificate.noExpiry')}
                            </span>
                            <bdi dir="ltr" className="font-mono">
                                {c.serial}
                            </bdi>
                        </span>
                    </div>
                    <div className="flex flex-wrap gap-1">
                        {c.pdfUrl ? (
                            <Button asChild variant="ghost" className="min-h-11">
                                <a href={c.pdfUrl} data-certificate-pdf>
                                    <Download aria-hidden />
                                    {t('certificate.download')}
                                </a>
                            </Button>
                        ) : null}
                        <Button asChild variant="ghost" className="min-h-11">
                            <a href={c.verifyUrl} target="_blank" rel="noreferrer">
                                <ExternalLink aria-hidden />
                                {t('certificate.check')}
                                <span className="sr-only">{t('learn.newTab')}</span>
                            </a>
                        </Button>
                        {onRevoke && c.state !== 'revoked' ? (
                            <Button
                                variant="ghost"
                                className="min-h-11 text-danger"
                                onClick={() => onRevoke(c)}
                            >
                                {t('certificate.revoke')}
                            </Button>
                        ) : null}
                    </div>
                </li>
            ))}
        </ul>
    );
}

/** Training taken outside the catalog (D105): its dates and cost, and its certificate. */
export function ExternalList({
    records,
    canUpload,
    courses,
    onUploaded,
}: {
    records: External[];
    canUpload: boolean;
    courses?: App.Domain.Training.Data.CourseRefData[];
    onUploaded?: () => void;
}) {
    const { t } = useTranslation('training');
    const { locale } = useLocale();

    return (
        <ul
            className="flex flex-col divide-y divide-border rounded-lg border border-border bg-card"
            data-external
        >
            {records.map((r) => (
                <ExternalRow
                    key={r.id}
                    record={r}
                    canUpload={canUpload}
                    courses={courses}
                    onUploaded={onUploaded}
                    locale={locale}
                    t={t}
                />
            ))}
        </ul>
    );
}

function ExternalRow({
    record,
    canUpload,
    courses,
    onUploaded,
    locale,
    t,
}: {
    record: External;
    canUpload: boolean;
    courses?: App.Domain.Training.Data.CourseRefData[];
    onUploaded?: () => void;
    locale: 'en' | 'ar';
    t: (key: string, options?: Record<string, unknown>) => string;
}) {
    const [uploading, setUploading] = useState(false);
    const [course, setCourse] = useState('');
    const id = `external-${record.id}`;
    const dates = [record.startsOn, record.endsOn]
        .filter((d): d is string => Boolean(d))
        .map((d) => formatDate(d, locale, { dateStyle: 'medium' }));

    return (
        <li className="flex flex-col gap-2 p-3" data-external-row={record.id}>
            <div className="flex flex-col gap-0.5">
                <span className="text-body font-medium">{record.title}</span>
                <span className="text-caption text-text-secondary">
                    {[
                        record.provider,
                        Array.from(new Set(dates)).join(' – '),
                        record.cost
                            ? formatNumber(
                                  record.cost.amountMinor / 10 ** minorDigits(record.cost.currency),
                                  locale,
                                  { style: 'currency', currency: record.cost.currency },
                              )
                            : null,
                    ]
                        .filter(Boolean)
                        .join(' · ')}
                </span>
                {record.course ? (
                    <span className="text-caption text-text-secondary">
                        {t('external.metBy', { course: record.course.title })}
                    </span>
                ) : null}
            </div>
            <div className="flex flex-wrap items-center gap-2">
                {record.certificateUrl ? (
                    <Button asChild variant="ghost" className="min-h-11">
                        <a href={record.certificateUrl}>
                            <Download aria-hidden />
                            {record.certificateName ?? t('certificate.download')}
                        </a>
                    </Button>
                ) : null}
                {canUpload ? (
                    <label
                        htmlFor={id}
                        className="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-md border border-border px-3 text-small hover:bg-muted"
                    >
                        <FileUp aria-hidden className="size-4" />
                        {record.certificateUrl ? t('external.replace') : t('external.upload')}
                        <input
                            id={id}
                            type="file"
                            accept="application/pdf,image/*"
                            className="sr-only"
                            disabled={uploading}
                            data-external-upload
                            onChange={(e) => {
                                const file = e.target.files?.[0];

                                if (!file) return;

                                setUploading(true);
                                router.post(
                                    training.external.upload.url(record.id),
                                    { file },
                                    {
                                        forceFormData: true,
                                        preserveScroll: true,
                                        onSuccess: onUploaded,
                                        onFinish: () => setUploading(false),
                                    },
                                );
                            }}
                        />
                    </label>
                ) : null}
                {courses && courses.length > 0 && !record.course ? (
                    <form
                        className="flex flex-wrap items-center gap-2"
                        onSubmit={(e) => {
                            e.preventDefault();

                            if (course)
                                router.post(
                                    training.external.link.url(record.id),
                                    { course },
                                    { preserveScroll: true, onSuccess: onUploaded },
                                );
                        }}
                    >
                        <label htmlFor={`${id}-course`} className="sr-only">
                            {t('external.catalogCourse')}
                        </label>
                        <select
                            id={`${id}-course`}
                            value={course}
                            onChange={(e) => setCourse(e.target.value)}
                            className="min-h-11 rounded-md border border-input bg-background px-3 text-small"
                        >
                            <option value="">{t('external.catalogCourse')}</option>
                            {courses.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.title}
                                </option>
                            ))}
                        </select>
                        <Button
                            type="submit"
                            variant="outline"
                            className="min-h-11"
                            disabled={!course}
                        >
                            {t('external.enrolInstead')}
                        </Button>
                    </form>
                ) : null}
            </div>
        </li>
    );
}
