import { Head, router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { CopyField } from '@/components/mw/copy-field';
import { Field } from '@/components/mw/field';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { StatusBadge } from '@/components/mw/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useLocale } from '@/hooks/use-locale';
import { formatDate } from '@/lib/format';
import { localized } from '@/lib/localized';
import attendance from '@/routes/attendance';

type Kiosk = {
    id: string;
    name: string;
    branch: Record<string, string>;
    active: boolean;
    lastSeenAt: string | null;
};
type Props = { kiosks: Kiosk[]; branches: Array<{ id: string; name: Record<string, string> }> };

/** Kiosks (attendance.configure): branch screens with rotating QR codes (D70). */
export default function Kiosks({ kiosks, branches }: Props) {
    const { t } = useTranslation('attendance');
    const { locale } = useLocale();
    const { flash } = usePage();
    const link = (flash as { kioskLink?: string } | undefined)?.kioskLink ?? null;
    const [adding, setAdding] = useState(false);
    const [name, setName] = useState('');
    const [branch, setBranch] = useState(branches[0]?.id ?? '');
    const [errors, setErrors] = useState<Record<string, string>>({});

    return (
        <>
            <Head title={t('kiosks.title')} />
            <PageHeader
                title={t('kiosks.title')}
                description={t('kiosks.description')}
                actions={
                    <Button className="min-h-11" onClick={() => setAdding(true)}>
                        <Plus aria-hidden className="size-4" />
                        {t('kiosks.add')}
                    </Button>
                }
            />

            {link ? (
                <div
                    role="status"
                    className="mb-6 rounded-lg border border-info-border bg-info-bg p-4"
                    data-kiosk-link
                >
                    <CopyField label={t('kiosks.link')} value={link} />
                </div>
            ) : null}

            {kiosks.length === 0 ? (
                <PageState
                    kind="empty"
                    illustration="empty-integrations"
                    title={t('kiosks.empty')}
                />
            ) : (
                <ul className="flex flex-col divide-y divide-border rounded-lg border border-border bg-card">
                    {kiosks.map((k) => (
                        <li
                            key={k.id}
                            className="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:gap-4"
                        >
                            <div className="flex min-w-0 flex-1 flex-col">
                                <span className="font-medium">{k.name}</span>
                                <span className="text-small text-text-secondary">
                                    {localized(k.branch, locale)} ·{' '}
                                    {k.lastSeenAt
                                        ? t('kiosks.lastSeen', {
                                              time: formatDate(k.lastSeenAt, locale, {
                                                  dateStyle: 'medium',
                                                  timeStyle: 'short',
                                              }),
                                          })
                                        : t('kiosks.neverSeen')}
                                </span>
                            </div>
                            {k.active ? (
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="min-h-11"
                                        onClick={() =>
                                            router.post(
                                                attendance.kiosks.relink.url(k.id),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        {t('kiosks.relink')}
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="min-h-11 text-danger"
                                        onClick={() =>
                                            router.delete(attendance.kiosks.destroy.url(k.id), {
                                                preserveScroll: true,
                                            })
                                        }
                                    >
                                        {t('kiosks.retire')}
                                    </Button>
                                </div>
                            ) : (
                                <StatusBadge status="cancelled" label={t('kiosks.retired')} />
                            )}
                        </li>
                    ))}
                </ul>
            )}

            <ResponsiveDialog
                open={adding}
                onOpenChange={setAdding}
                title={t('kiosks.add')}
                footer={
                    <Button
                        type="button"
                        disabled={name.trim() === '' || !branch}
                        onClick={() =>
                            router.post(
                                attendance.kiosks.store.url(),
                                { name, branchId: branch },
                                {
                                    preserveScroll: true,
                                    onError: setErrors,
                                    onSuccess: () => setAdding(false),
                                },
                            )
                        }
                    >
                        {t('kiosks.create')}
                    </Button>
                }
            >
                <div className="flex flex-col gap-4">
                    <Field id="kiosk-name" label={t('kiosks.name')} error={errors.name} required>
                        <Input
                            id="kiosk-name"
                            value={name}
                            maxLength={120}
                            onChange={(e) => setName(e.target.value)}
                        />
                    </Field>
                    <Field
                        id="kiosk-branch"
                        label={t('kiosks.branch')}
                        error={errors.branchId}
                        required
                    >
                        <Select value={branch} onValueChange={setBranch}>
                            <SelectTrigger id="kiosk-branch" className="min-h-11">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {branches.map((b) => (
                                    <SelectItem key={b.id} value={b.id}>
                                        {localized(b.name, locale)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>
                </div>
            </ResponsiveDialog>
        </>
    );
}
