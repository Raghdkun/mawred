import { Trash2 } from 'lucide-react';
import { type ReactNode, useId } from 'react';
import { useTranslation } from 'react-i18next';
import { AsyncPicker } from '@/components/mw/async-picker';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { ConditionEditor } from '@/features/forms/builder/rule-editors';
import { type FormField } from '@/features/forms/schema';
import { useLocale } from '@/hooks/use-locale';
import { localized } from '@/lib/localized';
import { type NodeConfig, type WorkflowNodeType } from './graph';

export type Catalog = {
    fields: Array<{
        key: string;
        label: Record<string, string> | null;
        type: string;
        options: unknown;
    }>;
    roles: Array<{ name: string; label: Record<string, string> }>;
    recordUpdates: Array<{ key: string; label: Record<string, string>; availableInPhase: number }>;
    workflows: Array<{ code: string; name: Record<string, string> }>;
    webhooks: Array<{ id: string; name: string }>;
};

type Spec = {
    type?: string;
    user?: string;
    userName?: string;
    role?: string;
    scoped?: boolean;
    level?: number;
    field?: string;
};

export const ASSIGNEE_TYPES = [
    'manager',
    'manager_level',
    'department_head',
    'branch_manager',
    'role',
    'round_robin',
    'user',
    'field',
    'requester',
    'assignee_manager',
] as const;

const selectClass =
    'h-10 w-full rounded-md border border-input bg-card px-3 text-sm text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-60';

function Row({
    label,
    htmlFor,
    children,
    hint,
}: {
    label: string;
    htmlFor: string;
    children: ReactNode;
    hint?: string;
}) {
    return (
        <div className="flex flex-col gap-1.5">
            <Label htmlFor={htmlFor}>{label}</Label>
            {children}
            {hint ? <p className="text-caption text-muted-foreground">{hint}</p> : null}
        </div>
    );
}

function TextPair({
    label,
    value,
    onChange,
    multiline,
    disabled,
}: {
    label: string;
    value: unknown;
    onChange: (value: Record<string, string>) => void;
    multiline?: boolean;
    disabled?: boolean;
}) {
    const { t } = useTranslation('workflows');
    const id = useId();
    const pair = (value as Record<string, string> | undefined) ?? {};
    const Control = multiline ? Textarea : Input;

    return (
        <fieldset className="flex flex-col gap-2" disabled={disabled}>
            <legend className="mb-1 text-sm font-medium">{label}</legend>
            {(['en', 'ar'] as const).map((locale) => (
                <div key={locale} className="flex flex-col gap-1">
                    <Label
                        htmlFor={`${id}-${locale}`}
                        className="text-caption font-normal text-muted-foreground"
                    >
                        {t(`settings.languages.${locale}`)}
                    </Label>
                    <Control
                        id={`${id}-${locale}`}
                        dir={locale === 'ar' ? 'rtl' : 'ltr'}
                        lang={locale}
                        value={pair[locale] ?? ''}
                        onChange={(event: { target: { value: string } }) =>
                            onChange({ ...pair, [locale]: event.target.value })
                        }
                    />
                </div>
            ))}
        </fieldset>
    );
}

export function AssigneeEditor({
    label,
    value,
    onChange,
    catalog,
    disabled,
    allowAssigneeManager = false,
}: {
    label: string;
    value: unknown;
    onChange: (spec: Spec) => void;
    catalog: Catalog;
    disabled?: boolean;
    allowAssigneeManager?: boolean;
}) {
    const { t } = useTranslation('workflows');
    const { locale } = useLocale();
    const id = useId();
    const spec = (value as Spec | undefined) ?? {};
    const types = ASSIGNEE_TYPES.filter(
        (type) => allowAssigneeManager || type !== 'assignee_manager',
    );
    const employeeFields = catalog.fields.filter((field) => field.type === 'employee');

    return (
        <fieldset
            className="flex flex-col gap-3 rounded-md border border-border p-3"
            disabled={disabled}
        >
            <legend className="px-1 text-sm font-medium">{label}</legend>
            <Row label={t('settings.assigneeType')} htmlFor={`${id}-type`}>
                <select
                    id={`${id}-type`}
                    className={selectClass}
                    value={spec.type ?? ''}
                    onChange={(event) => onChange({ type: event.target.value })}
                >
                    <option value="" disabled>
                        {t('settings.choose')}
                    </option>
                    {types.map((type) => (
                        <option key={type} value={type}>
                            {t(`assignees.${type}`)}
                        </option>
                    ))}
                </select>
            </Row>
            {spec.type === 'role' || spec.type === 'round_robin' ? (
                <>
                    <Row label={t('settings.role')} htmlFor={`${id}-role`}>
                        <select
                            id={`${id}-role`}
                            className={selectClass}
                            value={spec.role ?? ''}
                            onChange={(event) => onChange({ ...spec, role: event.target.value })}
                        >
                            <option value="" disabled>
                                {t('settings.choose')}
                            </option>
                            {catalog.roles.map((role) => (
                                <option key={role.name} value={role.name}>
                                    {localized(role.label, locale) || role.name}
                                </option>
                            ))}
                        </select>
                    </Row>
                    <div className="flex items-center justify-between gap-3">
                        <Label htmlFor={`${id}-scoped`} className="font-normal">
                            {t('settings.scoped')}
                        </Label>
                        <Switch
                            id={`${id}-scoped`}
                            checked={spec.scoped !== false}
                            onCheckedChange={(scoped) => onChange({ ...spec, scoped })}
                        />
                    </div>
                </>
            ) : null}
            {spec.type === 'manager_level' ? (
                <Row label={t('settings.level')} htmlFor={`${id}-level`}>
                    <Input
                        id={`${id}-level`}
                        type="number"
                        min={1}
                        max={10}
                        value={spec.level ?? 2}
                        onChange={(event) =>
                            onChange({ ...spec, level: Number(event.target.value) })
                        }
                    />
                </Row>
            ) : null}
            {spec.type === 'field' ? (
                <Row
                    label={t('settings.field')}
                    htmlFor={`${id}-field`}
                    hint={employeeFields.length === 0 ? t('settings.noEmployeeFields') : undefined}
                >
                    <select
                        id={`${id}-field`}
                        className={selectClass}
                        value={spec.field ?? ''}
                        onChange={(event) => onChange({ ...spec, field: event.target.value })}
                    >
                        <option value="" disabled>
                            {t('settings.choose')}
                        </option>
                        {employeeFields.map((field) => (
                            <option key={field.key} value={field.key}>
                                {localized(field.label ?? {}, locale) || field.key}
                            </option>
                        ))}
                    </select>
                </Row>
            ) : null}
            {spec.type === 'user' ? (
                <div className="flex flex-col gap-1.5">
                    <span className="text-sm font-medium">{t('settings.person')}</span>
                    <AsyncPicker<{ id: string; name: string }>
                        endpoint="delegates"
                        queryKey="workflow-users"
                        toOption={(user) => ({ id: user.id, label: user.name })}
                        value={
                            spec.user ? { id: spec.user, label: spec.userName ?? spec.user } : null
                        }
                        onChange={(option) =>
                            onChange({ ...spec, user: option?.id, userName: option?.label })
                        }
                        placeholder={t('settings.searchPeople')}
                        label={t('settings.person')}
                    />
                </div>
            ) : null}
        </fieldset>
    );
}

/** Settings for the selected node (handoff 15 side panel). Every change applies to the canvas at once. */
export function NodeSettings({
    nodeId,
    type,
    config,
    problems,
    catalog,
    readOnly,
    onChange,
    onDelete,
}: {
    nodeId: string;
    type: WorkflowNodeType;
    config: NodeConfig;
    problems: string[];
    catalog: Catalog;
    readOnly: boolean;
    onChange: (config: NodeConfig) => void;
    onDelete: () => void;
}) {
    const { t } = useTranslation('workflows');
    const { locale } = useLocale();
    const id = useId();
    const set = (key: string, value: unknown) => onChange({ ...config, [key]: value });
    const sla =
        (config.sla as
            { hours?: number; reminderHours?: number; escalateTo?: unknown } | undefined) ?? {};
    const hasLabel = !['start', 'end', 'split', 'join'].includes(type);

    return (
        <div className="flex flex-col gap-5">
            <div className="flex items-start justify-between gap-2">
                <div className="flex flex-col gap-0.5">
                    <h2 className="text-h4">{t(`nodes.${type}.name`)}</h2>
                    <p className="text-caption text-muted-foreground">{t(`nodes.${type}.help`)}</p>
                    <bdi dir="ltr" className="text-caption text-muted-foreground">
                        {nodeId}
                    </bdi>
                </div>
                {type !== 'start' && !readOnly ? (
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        onClick={onDelete}
                        aria-label={t('settings.delete')}
                    >
                        <Trash2 aria-hidden />
                    </Button>
                ) : null}
            </div>

            {problems.length > 0 ? (
                <ul
                    role="alert"
                    className="flex flex-col gap-1 rounded-md border border-danger-border bg-danger-bg p-3 text-small text-danger"
                >
                    {problems.map((problem) => (
                        <li key={problem}>{problem}</li>
                    ))}
                </ul>
            ) : null}

            <fieldset disabled={readOnly} className="flex flex-col gap-5">
                {hasLabel ? (
                    <TextPair
                        label={t('settings.label')}
                        value={config.label}
                        onChange={(label) => set('label', label)}
                    />
                ) : null}

                {type === 'approval' || type === 'task' ? (
                    <AssigneeEditor
                        label={t('settings.assignee')}
                        value={config.assignee}
                        onChange={(spec) => set('assignee', spec)}
                        catalog={catalog}
                    />
                ) : null}

                {type === 'task' ? (
                    <TextPair
                        label={t('settings.instructions')}
                        value={config.instructions}
                        multiline
                        onChange={(value) => set('instructions', value)}
                    />
                ) : null}

                {type === 'approval' ? (
                    <>
                        <Row label={t('settings.mode')} htmlFor={`${id}-mode`}>
                            <select
                                id={`${id}-mode`}
                                className={selectClass}
                                value={String(config.mode ?? 'any')}
                                onChange={(event) => set('mode', event.target.value)}
                            >
                                <option value="any">{t('settings.modes.any')}</option>
                                <option value="all">{t('settings.modes.all')}</option>
                            </select>
                        </Row>
                        <div className="flex items-center justify-between gap-3">
                            <Label htmlFor={`${id}-return`} className="font-normal">
                                {t('settings.allowReturn')}
                            </Label>
                            <Switch
                                id={`${id}-return`}
                                checked={config.allowReturn !== false}
                                onCheckedChange={(value) => set('allowReturn', value)}
                            />
                        </div>
                    </>
                ) : null}

                {type === 'approval' || type === 'task' ? (
                    <fieldset className="flex flex-col gap-3">
                        <legend className="mb-1 text-sm font-medium">{t('settings.sla')}</legend>
                        <div className="grid grid-cols-2 gap-3">
                            <Row label={t('settings.dueHours')} htmlFor={`${id}-hours`}>
                                <Input
                                    id={`${id}-hours`}
                                    type="number"
                                    min={0}
                                    value={sla.hours ?? ''}
                                    onChange={(event) =>
                                        set('sla', {
                                            ...sla,
                                            hours:
                                                event.target.value === ''
                                                    ? undefined
                                                    : Number(event.target.value),
                                        })
                                    }
                                />
                            </Row>
                            <Row label={t('settings.reminderHours')} htmlFor={`${id}-remind`}>
                                <Input
                                    id={`${id}-remind`}
                                    type="number"
                                    min={0}
                                    value={sla.reminderHours ?? ''}
                                    onChange={(event) =>
                                        set('sla', {
                                            ...sla,
                                            reminderHours:
                                                event.target.value === ''
                                                    ? undefined
                                                    : Number(event.target.value),
                                        })
                                    }
                                />
                            </Row>
                        </div>
                        {type === 'approval' ? (
                            <AssigneeEditor
                                label={t('settings.escalateTo')}
                                value={sla.escalateTo}
                                onChange={(spec) => set('sla', { ...sla, escalateTo: spec })}
                                catalog={catalog}
                                allowAssigneeManager
                            />
                        ) : null}
                    </fieldset>
                ) : null}

                {type === 'condition' ? (
                    <div className="flex flex-col gap-1.5">
                        <span className="text-sm font-medium">{t('settings.condition')}</span>
                        <ConditionEditor
                            id={`${id}-rule`}
                            value={config.rule}
                            onChange={(rule) => set('rule', rule)}
                            fields={catalog.fields as unknown as FormField[]}
                            includeRequester
                        />
                        <p className="text-caption text-muted-foreground">
                            {t('settings.conditionHelp')}
                        </p>
                    </div>
                ) : null}

                {type === 'notify' ? (
                    <>
                        <Row label={t('settings.channel')} htmlFor={`${id}-channel`}>
                            <select
                                id={`${id}-channel`}
                                className={selectClass}
                                value={String(config.channel ?? 'in_app')}
                                onChange={(event) => set('channel', event.target.value)}
                            >
                                <option value="in_app">{t('channels.in_app')}</option>
                                <option value="email">{t('channels.email')}</option>
                                <option value="whatsapp" disabled>
                                    {t('channels.whatsapp')}
                                </option>
                            </select>
                        </Row>
                        <AssigneeEditor
                            label={t('settings.recipients')}
                            value={config.to}
                            onChange={(spec) => set('to', spec)}
                            catalog={catalog}
                        />
                        <TextPair
                            label={t('settings.subject')}
                            value={config.subject}
                            onChange={(value) => set('subject', value)}
                        />
                        <TextPair
                            label={t('settings.message')}
                            value={config.body}
                            multiline
                            onChange={(value) => set('body', value)}
                        />
                        <p className="text-caption text-muted-foreground">
                            {t('settings.messageVariables')}{' '}
                            <bdi dir="ltr">
                                {'{{ request.number }} {{ request.type }} {{ requester.name }}'}
                            </bdi>
                        </p>
                    </>
                ) : null}

                {type === 'delay' ? (
                    <Row label={t('settings.waitHours')} htmlFor={`${id}-delay`}>
                        <Input
                            id={`${id}-delay`}
                            type="number"
                            min={1}
                            value={Number(config.hours ?? 24)}
                            onChange={(event) => set('hours', Number(event.target.value))}
                        />
                    </Row>
                ) : null}

                {type === 'update_record' ? (
                    <Row label={t('settings.update')} htmlFor={`${id}-update`}>
                        <select
                            id={`${id}-update`}
                            className={selectClass}
                            value={String(config.action ?? '')}
                            onChange={(event) => set('action', event.target.value)}
                        >
                            <option value="" disabled>
                                {t('settings.choose')}
                            </option>
                            {catalog.recordUpdates.map((update) => (
                                <option
                                    key={update.key}
                                    value={update.key}
                                    disabled={update.availableInPhase > 0}
                                >
                                    {localized(update.label, locale)}
                                    {update.availableInPhase > 0 ? ` (${t('settings.later')})` : ''}
                                </option>
                            ))}
                        </select>
                    </Row>
                ) : null}

                {type === 'webhook' ? (
                    <Row
                        label={t('settings.endpoint')}
                        htmlFor={`${id}-webhook`}
                        hint={catalog.webhooks.length === 0 ? t('settings.noWebhooks') : undefined}
                    >
                        <select
                            id={`${id}-webhook`}
                            className={selectClass}
                            value={String(config.endpoint ?? '')}
                            onChange={(event) => set('endpoint', event.target.value)}
                        >
                            <option value="" disabled>
                                {t('settings.choose')}
                            </option>
                            {catalog.webhooks.map((webhook) => (
                                <option key={webhook.id} value={webhook.id}>
                                    {webhook.name}
                                </option>
                            ))}
                        </select>
                    </Row>
                ) : null}

                {type === 'subworkflow' ? (
                    <Row label={t('settings.workflow')} htmlFor={`${id}-sub`}>
                        <select
                            id={`${id}-sub`}
                            className={selectClass}
                            value={String(config.workflow ?? '')}
                            onChange={(event) => set('workflow', event.target.value)}
                        >
                            <option value="" disabled>
                                {t('settings.choose')}
                            </option>
                            {catalog.workflows.map((workflow) => (
                                <option key={workflow.code} value={workflow.code}>
                                    {localized(workflow.name, locale) || workflow.code}
                                </option>
                            ))}
                        </select>
                    </Row>
                ) : null}

                {type === 'end' ? (
                    <Row label={t('settings.outcome')} htmlFor={`${id}-outcome`}>
                        <select
                            id={`${id}-outcome`}
                            className={selectClass}
                            value={String(config.outcome ?? 'completed')}
                            onChange={(event) => set('outcome', event.target.value)}
                        >
                            {['approved', 'rejected', 'completed'].map((outcome) => (
                                <option key={outcome} value={outcome}>
                                    {t(`outcomes.${outcome}`)}
                                </option>
                            ))}
                        </select>
                    </Row>
                ) : null}
            </fieldset>
        </div>
    );
}
