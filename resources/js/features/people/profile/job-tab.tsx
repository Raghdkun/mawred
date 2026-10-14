import { router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Field } from '@/components/mw/field';
import { Section } from '@/components/mw/page-header';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useLocale } from '@/hooks/use-locale';
import { formatDate } from '@/lib/format';
import { localized, personName } from '@/lib/localized';
import { cn } from '@/lib/utils';
import people from '@/routes/people';
import { PlacementFields, placementPayload, type PlacementValue } from '../placement-fields';
import { type Assignment, type Asset, type ProfileProps, today } from './types';

function placementFrom(assignment: Assignment | undefined, locale: string): PlacementValue {
    const option = (ref: { id: string; name: Record<string, string> } | null) =>
        ref ? { id: ref.id, label: localized(ref.name, locale) } : null;

    return {
        branch: option(assignment?.branch ?? null),
        department: option(assignment?.department ?? null),
        position: option(assignment?.position ?? null),
        manager: assignment?.manager
            ? { id: assignment.manager.id, label: personName(assignment.manager, locale) }
            : null,
        employmentTypeId: assignment?.employmentType?.id ?? null,
        gradeId: assignment?.grade?.id ?? null,
    };
}

export function JobTab({
    profile,
    assignments,
    assets,
    options,
    can,
}: Pick<ProfileProps, 'profile' | 'assignments' | 'assets' | 'options' | 'can'>) {
    const { t } = useTranslation('people');
    const { locale } = useLocale();
    const [dialog, setDialog] = useState<'change' | 'secondary' | null>(null);
    const [handingOver, setHandingOver] = useState(false);
    const current =
        assignments.find((a) => a.isPrimary && a.isCurrent) ?? assignments.find((a) => a.isPrimary);
    const date = (value: string | null) =>
        value ? formatDate(value, locale, { dateStyle: 'medium' }) : t('job.open');
    const employeeId = profile.summary.id;

    return (
        <div className="flex flex-col gap-6">
            <Section
                title={t('job.placements')}
                actions={
                    can.update ? (
                        <div className="flex flex-wrap gap-2">
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => setDialog('secondary')}
                            >
                                {t('job.addSecondary')}
                            </Button>
                            <Button size="sm" onClick={() => setDialog('change')}>
                                {t('job.change')}
                            </Button>
                        </div>
                    ) : null
                }
            >
                <ol className="relative flex flex-col gap-4 border-s border-border ps-6">
                    {assignments.map((assignment) => (
                        <li key={assignment.id} className="relative">
                            <span
                                aria-hidden
                                className={cn(
                                    'absolute -start-[1.95rem] top-1.5 size-3 rounded-full border-2 border-card',
                                    assignment.isCurrent ? 'bg-primary' : 'bg-muted-foreground/40',
                                )}
                            />
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="text-sm font-medium">
                                    {[
                                        assignment.position
                                            ? localized(assignment.position.name, locale)
                                            : null,
                                        assignment.branch
                                            ? localized(assignment.branch.name, locale)
                                            : null,
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </span>
                                <Badge variant={assignment.isPrimary ? 'secondary' : 'outline'}>
                                    {assignment.isPrimary ? t('job.primary') : t('job.secondary')}
                                </Badge>
                                {assignment.isCurrent ? <Badge>{t('job.current')}</Badge> : null}
                            </div>
                            <p className="text-caption text-muted-foreground">
                                {date(assignment.effectiveFrom)} – {date(assignment.effectiveTo)}
                                {assignment.department
                                    ? ` · ${localized(assignment.department.name, locale)}`
                                    : ''}
                                {assignment.manager
                                    ? ` · ${t('job.reportsTo', { name: personName(assignment.manager, locale) })}`
                                    : ''}
                            </p>
                            {can.update &&
                            !assignment.isPrimary &&
                            (assignment.isCurrent || !assignment.effectiveTo) ? (
                                <Button
                                    variant="link"
                                    size="sm"
                                    className="h-auto px-0"
                                    onClick={() =>
                                        router.patch(
                                            people.placements.secondary.end.url([
                                                employeeId,
                                                assignment.id,
                                            ]),
                                            { on: today() },
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    {t('job.endSecondary')}
                                </Button>
                            ) : null}
                        </li>
                    ))}
                </ol>
            </Section>

            <Section
                title={t('assets.title')}
                actions={
                    can.update ? (
                        <Button size="sm" variant="outline" onClick={() => setHandingOver(true)}>
                            <Plus aria-hidden />
                            {t('assets.handOver')}
                        </Button>
                    ) : null
                }
            >
                {assets.length === 0 ? (
                    <p className="text-small text-muted-foreground">{t('assets.none')}</p>
                ) : (
                    <ul className="flex flex-col divide-y divide-border">
                        {assets.map((asset) => (
                            <AssetRow
                                key={asset.id}
                                employeeId={employeeId}
                                asset={asset}
                                canUpdate={can.update}
                            />
                        ))}
                    </ul>
                )}
            </Section>

            {dialog ? (
                <PlacementDialog
                    mode={dialog}
                    employeeId={employeeId}
                    initial={
                        dialog === 'change'
                            ? placementFrom(current, locale)
                            : placementFrom(undefined, locale)
                    }
                    options={options}
                    onClose={() => setDialog(null)}
                />
            ) : null}
            {handingOver ? (
                <AssetDialog employeeId={employeeId} onClose={() => setHandingOver(false)} />
            ) : null}
        </div>
    );
}

function PlacementDialog({
    mode,
    employeeId,
    initial,
    options,
    onClose,
}: {
    mode: 'change' | 'secondary';
    employeeId: string;
    initial: PlacementValue;
    options: ProfileProps['options'];
    onClose: () => void;
}) {
    const { t } = useTranslation('people');
    const [placement, setPlacement] = useState(initial);
    const [effectiveFrom, setEffectiveFrom] = useState(today());
    const [effectiveTo, setEffectiveTo] = useState('');
    const [reason, setReason] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const submit = () =>
        router.post(
            mode === 'change'
                ? people.placements.change.url(employeeId)
                : people.placements.secondary.store.url(employeeId),
            {
                ...placementPayload(placement),
                effectiveFrom,
                effectiveTo: effectiveTo || null,
                reason: reason || null,
            },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onError: setErrors,
                onSuccess: onClose,
            },
        );

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={mode === 'change' ? t('job.change') : t('job.addSecondary')}
            description={mode === 'change' ? t('job.changeHelp') : t('job.secondaryHelp')}
            className="sm:max-w-3xl"
            footer={
                <>
                    <Button variant="outline" onClick={onClose}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button onClick={submit} loading={processing}>
                        {t('common:actions.save')}
                    </Button>
                </>
            }
        >
            <div className="flex flex-col gap-5">
                {errors.error ? (
                    <p role="alert" className="rounded-md bg-danger-bg p-3 text-small text-danger">
                        {errors.error}
                    </p>
                ) : null}
                <PlacementFields
                    value={placement}
                    onChange={setPlacement}
                    errors={errors}
                    employmentTypes={options.employmentTypes}
                    grades={options.grades}
                    showManager={mode === 'change'}
                />
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field
                        id="placement-from"
                        label={t('job.effectiveFrom')}
                        error={errors.effectiveFrom}
                        required
                    >
                        <Input
                            id="placement-from"
                            type="date"
                            dir="ltr"
                            value={effectiveFrom}
                            onChange={(event) => setEffectiveFrom(event.target.value)}
                        />
                    </Field>
                    {mode === 'secondary' ? (
                        <Field
                            id="placement-to"
                            label={t('job.effectiveTo')}
                            error={errors.effectiveTo}
                        >
                            <Input
                                id="placement-to"
                                type="date"
                                dir="ltr"
                                value={effectiveTo}
                                onChange={(event) => setEffectiveTo(event.target.value)}
                            />
                        </Field>
                    ) : null}
                </div>
                <Field id="placement-reason" label={t('job.reason')} error={errors.reason}>
                    <Textarea
                        id="placement-reason"
                        value={reason}
                        onChange={(event) => setReason(event.target.value)}
                        maxLength={300}
                    />
                </Field>
            </div>
        </ResponsiveDialog>
    );
}

function AssetRow({
    employeeId,
    asset,
    canUpdate,
}: {
    employeeId: string;
    asset: Asset;
    canUpdate: boolean;
}) {
    const { t } = useTranslation('people');
    const { locale } = useLocale();

    return (
        <li className="flex flex-wrap items-center gap-3 py-3">
            <span className="flex min-w-0 flex-1 flex-col">
                <span className="text-sm font-medium">{asset.name}</span>
                <span className="text-caption text-muted-foreground">
                    <bdi dir="ltr">{asset.assetTag}</bdi>
                    {asset.serialNumber ? (
                        <>
                            {' · '}
                            <bdi dir="ltr">{asset.serialNumber}</bdi>
                        </>
                    ) : null}
                    {' · '}
                    {formatDate(asset.assignedOn, locale, { dateStyle: 'medium' })}
                </span>
            </span>
            {asset.returnedOn ? (
                <Badge variant="outline">
                    {t('assets.returnedOn', {
                        date: formatDate(asset.returnedOn, locale, { dateStyle: 'medium' }),
                    })}
                </Badge>
            ) : canUpdate ? (
                <Button
                    size="sm"
                    variant="ghost"
                    onClick={() =>
                        router.patch(
                            people.assets.return.url([employeeId, asset.id]),
                            { returnedOn: today() },
                            { preserveScroll: true },
                        )
                    }
                >
                    {t('assets.markReturned')}
                </Button>
            ) : (
                <Badge>{t('assets.out')}</Badge>
            )}
        </li>
    );
}

function AssetDialog({ employeeId, onClose }: { employeeId: string; onClose: () => void }) {
    const { t } = useTranslation('people');
    const [data, setData] = useState({
        name: '',
        assetTag: '',
        category: '',
        serialNumber: '',
        assignedOn: today(),
        dueBackOn: '',
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const set = (key: keyof typeof data) => (event: React.ChangeEvent<HTMLInputElement>) =>
        setData({ ...data, [key]: event.target.value });

    const submit = () =>
        router.post(
            people.assets.store.url(employeeId),
            Object.fromEntries(Object.entries(data).map(([k, v]) => [k, v || null])),
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onError: setErrors,
                onSuccess: onClose,
            },
        );

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={t('assets.handOver')}
            footer={
                <>
                    <Button variant="outline" onClick={onClose}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button onClick={submit} loading={processing}>
                        {t('common:actions.save')}
                    </Button>
                </>
            }
        >
            <div className="grid gap-4 sm:grid-cols-2">
                <Field id="asset-name" label={t('assets.name')} error={errors.name} required>
                    <Input id="asset-name" value={data.name} onChange={set('name')} />
                </Field>
                <Field id="asset-tag" label={t('assets.tag')} error={errors.assetTag} required>
                    <Input
                        id="asset-tag"
                        dir="ltr"
                        value={data.assetTag}
                        onChange={set('assetTag')}
                    />
                </Field>
                <Field id="asset-category" label={t('assets.category')} error={errors.category}>
                    <Input id="asset-category" value={data.category} onChange={set('category')} />
                </Field>
                <Field id="asset-serial" label={t('assets.serial')} error={errors.serialNumber}>
                    <Input
                        id="asset-serial"
                        dir="ltr"
                        value={data.serialNumber}
                        onChange={set('serialNumber')}
                    />
                </Field>
                <Field
                    id="asset-assigned"
                    label={t('assets.assignedOn')}
                    error={errors.assignedOn}
                    required
                >
                    <Input
                        id="asset-assigned"
                        type="date"
                        dir="ltr"
                        value={data.assignedOn}
                        onChange={set('assignedOn')}
                    />
                </Field>
                <Field id="asset-due" label={t('assets.dueBackOn')} error={errors.dueBackOn}>
                    <Input
                        id="asset-due"
                        type="date"
                        dir="ltr"
                        value={data.dueBackOn}
                        onChange={set('dueBackOn')}
                    />
                </Field>
            </div>
        </ResponsiveDialog>
    );
}
