import { Head, router } from '@inertiajs/react';
import { ChevronDown, ChevronRight, Plus } from 'lucide-react';
import { type FormEvent, Fragment, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { type PickerOption } from '@/components/mw/async-picker';
import { DirIcon } from '@/components/mw/dir-icon';
import { Field } from '@/components/mw/field';
import { PageHeader } from '@/components/mw/page-header';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { CatalogSelect } from '@/features/organization/catalog-select';
import {
    BranchPicker,
    DepartmentPicker,
    EmployeePicker,
    PositionPicker,
} from '@/features/organization/pickers';
import { ImpactDialog } from '@/features/policies/impact-dialog';
import { PoliciesTabs } from '@/features/policies/policies-tabs';
import { type PolicyAssignment, type PolicyKey, type PolicyLevel } from '@/features/policies/types';
import { useImpact } from '@/features/policies/use-impact';
import { useLocale } from '@/hooks/use-locale';
import { formatDate } from '@/lib/format';
import { localized } from '@/lib/localized';
import assignmentRoutes from '@/routes/policies/assignments';

type Catalog = App.Domain.Organization.Data.CatalogEntryData;

type Props = {
    today: string;
    levels: PolicyLevel[];
    keys: PolicyKey[];
    assignments: PolicyAssignment[];
    sets: Array<{ id: string; name: Record<string, string> }>;
    options: { grades: Catalog[]; employmentTypes: Catalog[] };
    can: { update: boolean };
};

/** The six designed cards, general to specific: grade and employment type share one (D30). */
const CARDS: Array<{ key: string; levels: PolicyLevel[] }> = [
    { key: 'global', levels: ['global'] },
    { key: 'branch', levels: ['branch'] },
    { key: 'department', levels: ['department'] },
    { key: 'position', levels: ['position'] },
    { key: 'gradeAndType', levels: ['grade', 'employment_type'] },
    { key: 'employee', levels: ['employee'] },
];

/**
 * Assignment & precedence (handoff 18): the chain runs in the reading direction, horizontal on
 * desktop and vertical on phones. Each card lists what is assigned at that level.
 */
export default function PolicyAssignments({ today, keys, assignments, sets, options, can }: Props) {
    const { t } = useTranslation('policies');
    const [assigning, setAssigning] = useState(false);
    const [ending, setEnding] = useState<PolicyAssignment | null>(null);

    return (
        <>
            <Head title={t('assignments.pageTitle')} />
            <PageHeader
                title={t('title')}
                description={t('description')}
                actions={
                    can.update ? (
                        <Button onClick={() => setAssigning(true)}>
                            <Plus aria-hidden />
                            {t('assignments.assign')}
                        </Button>
                    ) : null
                }
            />
            <PoliciesTabs />

            <section aria-labelledby="chain-title" className="flex flex-col gap-3">
                <div className="flex flex-col gap-1">
                    <h2 id="chain-title" className="text-h4">
                        {t('assignments.chain')}
                    </h2>
                    <p className="text-small text-text-secondary">{t('assignments.chainHelp')}</p>
                </div>
                <ol className="flex flex-col gap-2 lg:flex-row lg:items-stretch lg:gap-0">
                    {CARDS.map((card, index) => (
                        <Fragment key={card.key}>
                            {index > 0 ? (
                                <li
                                    aria-hidden
                                    className="flex items-center justify-center text-muted-foreground lg:px-1"
                                >
                                    <ChevronDown className="size-4 lg:hidden" />
                                    <DirIcon>
                                        <ChevronRight className="hidden size-4 lg:block" />
                                    </DirIcon>
                                </li>
                            ) : null}
                            <LevelCard
                                title={t(`levels.${card.key}`)}
                                assignments={assignments.filter((a) =>
                                    card.levels.includes(a.level as PolicyLevel),
                                )}
                                showLevel={card.levels.length > 1}
                                today={today}
                                canEnd={can.update}
                                onEnd={setEnding}
                            />
                        </Fragment>
                    ))}
                </ol>
            </section>

            {assigning ? (
                <AssignDialog
                    today={today}
                    keys={keys}
                    sets={sets}
                    options={options}
                    onClose={() => setAssigning(false)}
                />
            ) : null}
            {ending ? (
                <EndDialog assignment={ending} today={today} onClose={() => setEnding(null)} />
            ) : null}
        </>
    );
}

function LevelCard({
    title,
    assignments,
    showLevel,
    today,
    canEnd,
    onEnd,
}: {
    title: string;
    assignments: PolicyAssignment[];
    showLevel: boolean;
    today: string;
    canEnd: boolean;
    onEnd: (assignment: PolicyAssignment) => void;
}) {
    const { t } = useTranslation('policies');
    const { locale } = useLocale();

    return (
        <li className="flex min-w-0 flex-1 flex-col gap-3 rounded-lg bg-card p-4 shadow-sm inset-shadow-highlight">
            <div className="flex items-baseline justify-between gap-2">
                <h3 className="text-sm font-semibold">{title}</h3>
                <span className="text-caption text-muted-foreground tabular-nums">
                    {t('assignments.count', { count: assignments.length })}
                </span>
            </div>
            {assignments.length === 0 ? (
                <p className="text-caption text-muted-foreground">{t('assignments.nothing')}</p>
            ) : (
                <ul className="flex flex-col gap-2">
                    {assignments.map((assignment) => (
                        <li
                            key={assignment.id}
                            className="flex flex-col gap-1 rounded-md border border-border p-2.5"
                        >
                            <span className="truncate text-small font-medium">
                                {assignment.targetName ?? t('assignments.everyone')}
                            </span>
                            {showLevel ? (
                                <span className="text-caption text-muted-foreground">
                                    {t(`levels.${assignment.level}`)}
                                </span>
                            ) : null}
                            <span className="truncate text-caption text-text-secondary">
                                {localized(assignment.setName, locale)}
                            </span>
                            <span className="text-caption text-muted-foreground">
                                {assignment.effectiveFrom > today
                                    ? t('assignments.scheduled', {
                                          date: formatDate(assignment.effectiveFrom, locale),
                                      })
                                    : null}
                                {assignment.effectiveTo
                                    ? ` ${t('sets.until', { date: formatDate(assignment.effectiveTo, locale) })}`
                                    : null}
                            </span>
                            {canEnd ? (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="self-start"
                                    onClick={() => onEnd(assignment)}
                                >
                                    {t('assignments.end')}
                                </Button>
                            ) : null}
                        </li>
                    ))}
                </ul>
            )}
        </li>
    );
}

function AssignDialog({
    today,
    keys,
    sets,
    options,
    onClose,
}: {
    today: string;
    keys: PolicyKey[];
    sets: Props['sets'];
    options: Props['options'];
    onClose: () => void;
}) {
    const { t } = useTranslation('policies');
    const { locale } = useLocale();
    const [setId, setSetId] = useState<string>('');
    const [level, setLevel] = useState<PolicyLevel>('branch');
    const [target, setTarget] = useState<PickerOption | null>(null);
    const [catalogTarget, setCatalogTarget] = useState<string | null>(null);
    const [effectiveFrom, setEffectiveFrom] = useState(today);
    const [reason, setReason] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);
    const impact = useImpact();

    const targetId =
        level === 'grade' || level === 'employment_type' ? catalogTarget : (target?.id ?? null);
    const payload = {
        setId,
        level,
        targetId: level === 'global' ? null : targetId,
        effectiveFrom,
        reason: reason || null,
    };

    const save = () => {
        setSaving(true);
        router.post(assignmentRoutes.store.url(), payload, {
            preserveScroll: true,
            onSuccess: onClose,
            onError: (bag) => {
                setErrors(bag);
                impact.setOpen(false);
            },
            onFinish: () => setSaving(false),
        });
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        void impact.run(assignmentRoutes.preview.url(), payload);
    };

    const pickerProps = { value: target, onChange: setTarget, label: t('assignments.target') };

    return (
        <>
            <ResponsiveDialog
                open={!impact.open}
                onOpenChange={(open) => !open && onClose()}
                title={t('assignments.assign')}
                footer={
                    <>
                        <Button variant="outline" onClick={onClose}>
                            {t('common:actions.cancel')}
                        </Button>
                        <Button type="submit" form="assign-form" disabled={!setId}>
                            {t('editor.preview')}
                        </Button>
                    </>
                }
            >
                <form id="assign-form" onSubmit={submit} className="flex flex-col gap-4" noValidate>
                    {errors.error ? (
                        <p
                            role="alert"
                            className="rounded-md bg-danger-bg p-3 text-small text-danger"
                        >
                            {errors.error}
                        </p>
                    ) : null}
                    <Field
                        id="assign-set"
                        label={t('assignments.set')}
                        error={errors.setId}
                        required
                    >
                        <Select value={setId} onValueChange={setSetId}>
                            <SelectTrigger id="assign-set">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {sets.map((set) => (
                                    <SelectItem key={set.id} value={set.id}>
                                        {localized(set.name, locale)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>
                    <Field
                        id="assign-level"
                        label={t('assignments.level')}
                        error={errors.level}
                        required
                    >
                        <Select
                            value={level}
                            onValueChange={(next) => {
                                setLevel(next as PolicyLevel);
                                setTarget(null);
                                setCatalogTarget(null);
                            }}
                        >
                            <SelectTrigger id="assign-level">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {(
                                    [
                                        'global',
                                        'branch',
                                        'department',
                                        'position',
                                        'grade',
                                        'employment_type',
                                        'employee',
                                    ] as const
                                ).map((value) => (
                                    <SelectItem key={value} value={value}>
                                        {t(`levels.${value}`)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>
                    {level === 'global' ? null : (
                        <div className="flex flex-col gap-1.5">
                            {level === 'branch' ? <BranchPicker {...pickerProps} /> : null}
                            {level === 'department' ? <DepartmentPicker {...pickerProps} /> : null}
                            {level === 'position' ? <PositionPicker {...pickerProps} /> : null}
                            {level === 'employee' ? <EmployeePicker {...pickerProps} /> : null}
                            {level === 'grade' || level === 'employment_type' ? (
                                <Field id="assign-catalog" label={t('assignments.target')} required>
                                    <CatalogSelect
                                        id="assign-catalog"
                                        value={catalogTarget}
                                        onChange={setCatalogTarget}
                                        options={
                                            level === 'grade'
                                                ? options.grades
                                                : options.employmentTypes
                                        }
                                        allowNone={false}
                                    />
                                </Field>
                            ) : null}
                            {errors.targetId ? (
                                <p className="text-caption text-danger">{errors.targetId}</p>
                            ) : null}
                        </div>
                    )}
                    <Field
                        id="assign-from"
                        label={t('assignments.from')}
                        error={errors.effectiveFrom}
                        required
                    >
                        <Input
                            id="assign-from"
                            type="date"
                            dir="ltr"
                            className="w-44"
                            value={effectiveFrom}
                            onChange={(event) => setEffectiveFrom(event.target.value)}
                        />
                    </Field>
                    {effectiveFrom < today ? (
                        <Field
                            id="assign-reason"
                            label={t('assignments.reason')}
                            hint={t('editor.reasonHint')}
                            error={errors.reason}
                            required
                        >
                            <Input
                                id="assign-reason"
                                value={reason}
                                onChange={(event) => setReason(event.target.value)}
                            />
                        </Field>
                    ) : null}
                </form>
            </ResponsiveDialog>
            <ImpactDialog
                open={impact.open}
                onOpenChange={impact.setOpen}
                preview={impact.preview}
                loading={impact.loading}
                failed={impact.failed}
                keys={keys}
                onConfirm={save}
                confirming={saving}
            />
        </>
    );
}

function EndDialog({
    assignment,
    today,
    onClose,
}: {
    assignment: PolicyAssignment;
    today: string;
    onClose: () => void;
}) {
    const { t } = useTranslation('policies');
    const [endsOn, setEndsOn] = useState(today);
    const [error, setError] = useState<string | null>(null);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.delete(assignmentRoutes.destroy.url(assignment.id), {
            data: { endsOn },
            preserveScroll: true,
            onSuccess: onClose,
            onError: (bag) => setError(bag.endsOn ?? bag.error ?? null),
        });
    };

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={t('assignments.endTitle')}
            description={assignment.targetName ?? t('assignments.everyone')}
            footer={
                <>
                    <Button variant="outline" onClick={onClose}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="submit" form="end-assignment-form">
                        {t('assignments.end')}
                    </Button>
                </>
            }
        >
            <form id="end-assignment-form" onSubmit={submit} noValidate>
                <Field
                    id="ends-on"
                    label={t('assignments.endsOn')}
                    error={error ?? undefined}
                    required
                >
                    <Input
                        id="ends-on"
                        type="date"
                        dir="ltr"
                        className="w-44"
                        value={endsOn}
                        onChange={(event) => setEndsOn(event.target.value)}
                    />
                </Field>
            </form>
        </ResponsiveDialog>
    );
}
