import { router } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Row = { key: string; id: string | null; name: { en: string; ar: string } };

/** Scorecard criteria (D90): what interviewers rate 1–4, for a requisition or the defaults. */
export function CriteriaEditor({
    criteria,
    saveUrl,
    onSaved,
}: {
    criteria: Array<{ id: string; name: { en: string; ar: string } }>;
    saveUrl: string;
    onSaved?: () => void;
}) {
    const { t } = useTranslation('hiring');
    const [rows, setRows] = useState<Row[]>(() =>
        criteria.map((c) => ({ key: c.id, id: c.id, name: { ...c.name } })),
    );
    const [errors, setErrors] = useState<Record<string, string>>({});
    const update = (i: number, name: Row['name']) =>
        setRows((all) => all.map((r, j) => (j === i ? { ...r, name } : r)));

    return (
        <form
            className="flex flex-col gap-3"
            onSubmit={(e) => {
                e.preventDefault();
                router.put(
                    saveUrl,
                    { criteria: rows.map(({ id, name }) => ({ id, name })) },
                    { preserveScroll: true, onError: setErrors, onSuccess: () => onSaved?.() },
                );
            }}
            data-criteria-editor
        >
            {errors.criteria || errors.error ? (
                <p role="alert" className="text-caption text-danger">
                    {errors.criteria ?? errors.error}
                </p>
            ) : null}
            <ol className="flex flex-col gap-2">
                {rows.map((row, i) => (
                    <li
                        key={row.key}
                        className="grid gap-2 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-end"
                    >
                        <label className="flex flex-col gap-1 text-caption text-text-secondary">
                            {t('stages.nameEn')}
                            <Input
                                dir="ltr"
                                value={row.name.en}
                                required
                                maxLength={60}
                                onChange={(e) => update(i, { ...row.name, en: e.target.value })}
                            />
                        </label>
                        <label className="flex flex-col gap-1 text-caption text-text-secondary">
                            {t('stages.nameAr')}
                            <Input
                                dir="rtl"
                                lang="ar"
                                value={row.name.ar}
                                maxLength={60}
                                onChange={(e) => update(i, { ...row.name, ar: e.target.value })}
                            />
                        </label>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-11 text-danger"
                            aria-label={t('criteria.remove', { name: row.name.en })}
                            disabled={rows.length <= 1}
                            onClick={() => setRows((all) => all.filter((_, j) => j !== i))}
                        >
                            <Trash2 aria-hidden className="size-4" />
                        </Button>
                    </li>
                ))}
            </ol>
            <div className="flex flex-wrap justify-between gap-2">
                <Button
                    type="button"
                    variant="outline"
                    className="min-h-11"
                    disabled={rows.length >= 10}
                    onClick={() =>
                        setRows((all) => [
                            ...all,
                            {
                                key: `new-${all.length}-${Math.random().toString(36).slice(2)}`,
                                id: null,
                                name: { en: '', ar: '' },
                            },
                        ])
                    }
                >
                    <Plus aria-hidden />
                    {t('criteria.add')}
                </Button>
                <Button type="submit" className="min-h-11" data-save-criteria>
                    {t('criteria.save')}
                </Button>
            </div>
        </form>
    );
}
