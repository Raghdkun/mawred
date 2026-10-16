import { router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Stage = App.Domain.Hiring.Data.StageData;
type Draft = { key: string; id: string | null; name: { en: string; ar: string }; kind: string };

const KINDS = ['applied', 'screening', 'interview', 'offer', 'hired'] as const;

/**
 * Pipeline stages (handoff 29 "stage settings"): rename in both languages, reorder, add and
 * remove. The first stage stays "applied" and the last "hired"; the server refuses removing a
 * stage that still has candidates.
 */
export function StageEditor({
    stages,
    saveUrl,
    onSaved,
    idPrefix = 'stage',
}: {
    stages: Stage[];
    saveUrl: string;
    onSaved?: () => void;
    idPrefix?: string;
}) {
    const { t } = useTranslation('hiring');
    const [rows, setRows] = useState<Draft[]>(() =>
        stages.map((s) => ({ key: s.id, id: s.id, name: { ...s.names }, kind: s.kind })),
    );
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);
    const update = (i: number, next: Partial<Draft>) =>
        setRows((list) => list.map((row, j) => (j === i ? { ...row, ...next } : row)));
    const swap = (i: number, j: number) =>
        setRows((list) => {
            const copy = [...list];
            [copy[i], copy[j]] = [copy[j]!, copy[i]!];

            return copy;
        });

    return (
        <form
            className="flex flex-col gap-4"
            onSubmit={(event) => {
                event.preventDefault();
                setSaving(true);
                router.put(
                    saveUrl,
                    { stages: rows.map(({ id, name, kind }) => ({ id, name, kind })) },
                    {
                        preserveScroll: true,
                        onError: setErrors,
                        onSuccess: () => onSaved?.(),
                        onFinish: () => setSaving(false),
                    },
                );
            }}
        >
            {errors.stages || errors.error ? (
                <p role="alert" className="rounded-md bg-danger-bg p-3 text-small text-danger">
                    {errors.stages ?? errors.error}
                </p>
            ) : null}
            <ol className="flex flex-col gap-3">
                {rows.map((row, i) => (
                    <li
                        key={row.key}
                        className="grid gap-2 rounded-md border border-border p-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_9rem_auto] sm:items-end"
                        data-stage-row={i}
                    >
                        <label className="flex flex-col gap-1 text-caption text-text-secondary">
                            {t('stages.nameEn')}
                            <Input
                                id={`${idPrefix}-${i}-en`}
                                dir="ltr"
                                value={row.name.en}
                                required
                                maxLength={40}
                                onChange={(e) =>
                                    update(i, { name: { ...row.name, en: e.target.value } })
                                }
                            />
                        </label>
                        <label className="flex flex-col gap-1 text-caption text-text-secondary">
                            {t('stages.nameAr')}
                            <Input
                                id={`${idPrefix}-${i}-ar`}
                                dir="rtl"
                                lang="ar"
                                value={row.name.ar}
                                maxLength={40}
                                onChange={(e) =>
                                    update(i, { name: { ...row.name, ar: e.target.value } })
                                }
                            />
                        </label>
                        <label className="flex flex-col gap-1 text-caption text-text-secondary">
                            <span id={`${idPrefix}-${i}-kind-label`}>{t('stages.kind')}</span>
                            <Select value={row.kind} onValueChange={(kind) => update(i, { kind })}>
                                <SelectTrigger
                                    className="min-h-11"
                                    aria-labelledby={`${idPrefix}-${i}-kind-label`}
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {KINDS.map((kind) => (
                                        <SelectItem key={kind} value={kind}>
                                            {t(`stageKind.${kind}`)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </label>
                        <div className="flex gap-1">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-11"
                                aria-label={t('stages.up', { name: row.name.en })}
                                disabled={i === 0}
                                onClick={() => swap(i, i - 1)}
                            >
                                <ArrowUp aria-hidden className="size-4" />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-11"
                                aria-label={t('stages.down', { name: row.name.en })}
                                disabled={i === rows.length - 1}
                                onClick={() => swap(i, i + 1)}
                            >
                                <ArrowDown aria-hidden className="size-4" />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-11 text-danger"
                                aria-label={t('stages.remove', { name: row.name.en })}
                                disabled={rows.length <= 2}
                                onClick={() => setRows((list) => list.filter((_, j) => j !== i))}
                            >
                                <Trash2 aria-hidden className="size-4" />
                            </Button>
                        </div>
                    </li>
                ))}
            </ol>
            <p className="text-caption text-text-secondary">{t('stages.rule')}</p>
            <div className="flex flex-wrap justify-between gap-2">
                <Button
                    type="button"
                    variant="outline"
                    className="min-h-11"
                    disabled={rows.length >= 12}
                    onClick={() =>
                        setRows((list) => [
                            ...list.slice(0, -1),
                            {
                                key: `new-${list.length}-${Math.random().toString(36).slice(2)}`,
                                id: null,
                                name: { en: '', ar: '' },
                                kind: 'interview',
                            },
                            ...list.slice(-1),
                        ])
                    }
                >
                    <Plus aria-hidden />
                    {t('stages.add')}
                </Button>
                <Button type="submit" className="min-h-11" disabled={saving} data-save-stages>
                    {t('stages.save')}
                </Button>
            </div>
        </form>
    );
}
