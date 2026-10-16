import { Head, router } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { type PickerOption } from '@/components/mw/async-picker';
import { type Bilingual, BilingualInput } from '@/components/mw/bilingual-input';
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
import { BranchPicker, DepartmentPicker } from '@/features/organization/pickers';
import { useLocale } from '@/hooks/use-locale';
import { formatDate, formatNumber } from '@/lib/format';
import { localized } from '@/lib/localized';
import bookingPages from '@/routes/booking-pages';

type Window = { weekday: number; from: string; to: string };
type Page = {
    id: string;
    title: Record<string, string>;
    description: Record<string, string>;
    location: string | null;
    durationMinutes: number;
    bufferMinutes: number;
    windows: Window[];
    timezone: string;
    startsOn: string;
    endsOn: string | null;
    audience: 'everyone' | 'branch' | 'department';
    branchId: string | null;
    departmentId: string | null;
    active: boolean;
};
type Props = { pages: Page[]; timezone: string };

/** My booking pages (D77): weekly hours cut into slots that colleagues in the audience book. */
export default function BookingPages({ pages, timezone }: Props) {
    const { t } = useTranslation('calendar');
    const { locale } = useLocale();
    const [editing, setEditing] = useState<Page | 'new' | null>(null);

    return (
        <>
            <Head title={t('pages.title')} />
            <PageHeader
                title={t('pages.title')}
                description={t('pages.description')}
                actions={
                    <Button className="min-h-11" onClick={() => setEditing('new')}>
                        <Plus aria-hidden className="size-4" />
                        {t('pages.add')}
                    </Button>
                }
            />
            {pages.length === 0 ? (
                <PageState kind="empty" illustration="empty-schedule" title={t('pages.empty')} />
            ) : (
                <ul className="flex flex-col divide-y divide-border rounded-lg border border-border bg-card">
                    {pages.map((p) => (
                        <li
                            key={p.id}
                            className="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:gap-4"
                        >
                            <div className="flex min-w-0 flex-1 flex-col">
                                <span className="font-medium">{localized(p.title, locale)}</span>
                                <span className="text-small text-text-secondary">
                                    {t('booking.length', {
                                        count: formatNumber(p.durationMinutes, locale),
                                    })}{' '}
                                    ·{' '}
                                    {p.windows
                                        .map(
                                            (w) =>
                                                `${formatDate(new Date(Date.UTC(2024, 0, w.weekday)), locale, { weekday: 'short', timeZone: 'UTC' })} ${w.from}–${w.to}`,
                                        )
                                        .join('، ')}
                                </span>
                            </div>
                            {p.active ? (
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="min-h-11"
                                        onClick={() => setEditing(p)}
                                    >
                                        {t('event.edit')}
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="min-h-11 text-danger"
                                        onClick={() =>
                                            router.delete(bookingPages.destroy.url(p.id), {
                                                preserveScroll: true,
                                            })
                                        }
                                    >
                                        {t('pages.retire')}
                                    </Button>
                                </div>
                            ) : (
                                <StatusBadge status="cancelled" label={t('pages.closed')} />
                            )}
                        </li>
                    ))}
                </ul>
            )}
            {editing ? (
                <PageDialog
                    page={editing === 'new' ? null : editing}
                    timezone={timezone}
                    onClose={() => setEditing(null)}
                />
            ) : null}
        </>
    );
}

function PageDialog({
    page,
    timezone,
    onClose,
}: {
    page: Page | null;
    timezone: string;
    onClose: () => void;
}) {
    const { t } = useTranslation('calendar');
    const { locale } = useLocale();
    const [title, setTitle] = useState<Bilingual>({
        en: page?.title.en ?? '',
        ar: page?.title.ar ?? '',
    });
    const [about, setAbout] = useState<Bilingual>({
        en: page?.description.en ?? '',
        ar: page?.description.ar ?? '',
    });
    const [location, setLocation] = useState(page?.location ?? '');
    const [duration, setDuration] = useState(page?.durationMinutes ?? 30);
    const [buffer, setBuffer] = useState(String(page?.bufferMinutes ?? 0));
    const [windows, setWindows] = useState<Window[]>(
        page?.windows ?? [{ weekday: 1, from: '10:00', to: '12:00' }],
    );
    const [startsOn, setStartsOn] = useState(
        page?.startsOn ?? new Date().toISOString().slice(0, 10),
    );
    const [endsOn, setEndsOn] = useState(page?.endsOn ?? '');
    const [audience, setAudience] = useState<Page['audience']>(page?.audience ?? 'everyone');
    const [branch, setBranch] = useState<PickerOption | null>(
        page?.branchId ? { id: page.branchId, label: '' } : null,
    );
    const [department, setDepartment] = useState<PickerOption | null>(
        page?.departmentId ? { id: page.departmentId, label: '' } : null,
    );
    const [errors, setErrors] = useState<Record<string, string>>({});
    const weekday = (d: number) =>
        formatDate(new Date(Date.UTC(2024, 0, d)), locale, { weekday: 'long', timeZone: 'UTC' });

    const save = () => {
        const payload = {
            title,
            description: about,
            location: location.trim() || null,
            durationMinutes: duration,
            bufferMinutes: Number(buffer) || 0,
            windows,
            timezone: page?.timezone ?? timezone,
            startsOn,
            endsOn: endsOn || null,
            audience,
            branchId: audience === 'branch' ? (branch?.id ?? null) : null,
            departmentId: audience === 'department' ? (department?.id ?? null) : null,
        };
        const options = { preserveScroll: true, onError: setErrors, onSuccess: onClose };

        if (page) {
            router.put(bookingPages.update.url(page.id), payload, options);
        } else {
            router.post(bookingPages.store.url(), payload, options);
        }
    };

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={page ? t('pages.edit') : t('pages.add')}
            className="sm:max-w-xl"
            footer={
                <>
                    <Button type="button" variant="outline" onClick={onClose}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button
                        type="button"
                        onClick={save}
                        disabled={title.en.trim() === ''}
                        data-save-booking-page
                    >
                        {t('pages.save')}
                    </Button>
                </>
            }
        >
            <div className="flex flex-col gap-4">
                <BilingualInput
                    id="page-title"
                    label={t('pages.name')}
                    value={title}
                    onChange={setTitle}
                    errors={errors}
                    errorKey="title"
                />
                <BilingualInput
                    id="page-about"
                    label={t('pages.about')}
                    value={about}
                    onChange={setAbout}
                    errors={errors}
                    errorKey="description"
                />
                <div className="grid grid-cols-2 gap-3">
                    <Field
                        id="page-length"
                        label={t('pages.length')}
                        error={errors.durationMinutes}
                    >
                        <Select
                            value={String(duration)}
                            onValueChange={(v) => setDuration(Number(v))}
                        >
                            <SelectTrigger id="page-length" className="min-h-11">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {[15, 20, 30, 45, 60, 90, 120].map((m) => (
                                    <SelectItem key={m} value={String(m)}>
                                        {t('booking.length', { count: formatNumber(m, locale) })}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>
                    <Field id="page-buffer" label={t('pages.buffer')} error={errors.bufferMinutes}>
                        <Input
                            id="page-buffer"
                            inputMode="numeric"
                            value={buffer}
                            onChange={(e) => setBuffer(e.target.value)}
                        />
                    </Field>
                </div>
                <fieldset className="flex flex-col gap-2">
                    <legend className="mb-1 text-small font-medium">{t('pages.windows')}</legend>
                    {errors.windows ? (
                        <p className="text-small text-danger">{errors.windows}</p>
                    ) : null}
                    {windows.map((w, i) => (
                        <div
                            key={i}
                            className="grid grid-cols-[1fr_auto_auto_auto] items-end gap-2"
                        >
                            <Select
                                value={String(w.weekday)}
                                onValueChange={(v) =>
                                    setWindows(
                                        windows.map((x, j) =>
                                            j === i ? { ...x, weekday: Number(v) } : x,
                                        ),
                                    )
                                }
                            >
                                <SelectTrigger aria-label={t('pages.weekday')} className="min-h-11">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {[7, 1, 2, 3, 4, 5, 6].map((d) => (
                                        <SelectItem key={d} value={String(d)}>
                                            {weekday(d)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Input
                                aria-label={t('pages.from')}
                                type="time"
                                dir="ltr"
                                className="w-28"
                                value={w.from}
                                onChange={(e) =>
                                    setWindows(
                                        windows.map((x, j) =>
                                            j === i ? { ...x, from: e.target.value } : x,
                                        ),
                                    )
                                }
                            />
                            <Input
                                aria-label={t('pages.to')}
                                type="time"
                                dir="ltr"
                                className="w-28"
                                value={w.to}
                                onChange={(e) =>
                                    setWindows(
                                        windows.map((x, j) =>
                                            j === i ? { ...x, to: e.target.value } : x,
                                        ),
                                    )
                                }
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-11"
                                aria-label={t('pages.removeWindow')}
                                disabled={windows.length === 1}
                                onClick={() => setWindows(windows.filter((_, j) => j !== i))}
                            >
                                <X aria-hidden className="size-4" />
                            </Button>
                        </div>
                    ))}
                    <Button
                        type="button"
                        variant="outline"
                        className="min-h-11 self-start"
                        onClick={() =>
                            setWindows([...windows, { weekday: 1, from: '10:00', to: '12:00' }])
                        }
                    >
                        <Plus aria-hidden className="size-4" />
                        {t('pages.addWindow')}
                    </Button>
                </fieldset>
                <div className="grid grid-cols-2 gap-3">
                    <Field id="page-from" label={t('pages.startsOn')} error={errors.startsOn}>
                        <Input
                            id="page-from"
                            type="date"
                            value={startsOn}
                            onChange={(e) => setStartsOn(e.target.value)}
                        />
                    </Field>
                    <Field id="page-until" label={t('pages.endsOn')} error={errors.endsOn}>
                        <Input
                            id="page-until"
                            type="date"
                            min={startsOn}
                            value={endsOn}
                            onChange={(e) => setEndsOn(e.target.value)}
                        />
                    </Field>
                </div>
                <Field id="page-location" label={t('pages.location')} error={errors.location}>
                    <Input
                        id="page-location"
                        value={location}
                        maxLength={200}
                        onChange={(e) => setLocation(e.target.value)}
                    />
                </Field>
                <Field id="page-audience" label={t('pages.audience')}>
                    <Select
                        value={audience}
                        onValueChange={(v) => setAudience(v as Page['audience'])}
                    >
                        <SelectTrigger id="page-audience" className="min-h-11">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="everyone">{t('pages.everyone')}</SelectItem>
                            <SelectItem value="branch">{t('pages.branch')}</SelectItem>
                            <SelectItem value="department">{t('pages.department')}</SelectItem>
                        </SelectContent>
                    </Select>
                </Field>
                {audience === 'branch' ? (
                    <BranchPicker label={t('pages.branch')} value={branch} onChange={setBranch} />
                ) : null}
                {audience === 'department' ? (
                    <DepartmentPicker
                        label={t('pages.department')}
                        value={department}
                        onChange={setDepartment}
                    />
                ) : null}
                {errors.branchId || errors.departmentId ? (
                    <p className="text-small text-danger">
                        {errors.branchId ?? errors.departmentId}
                    </p>
                ) : null}
            </div>
        </ResponsiveDialog>
    );
}
