import { router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Field } from '@/components/mw/field';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useLocale } from '@/hooks/use-locale';
import { formatDate } from '@/lib/format';
import roster from '@/routes/roster';

/** People needed per weekday at a branch (the planner's coverage row). */
export function CoverageDialog({
    open,
    onClose,
    branchId,
    coverage,
}: {
    open: boolean;
    onClose: () => void;
    branchId: string;
    coverage: Record<string, number>;
}) {
    const { t } = useTranslation('schedule');
    const { locale } = useLocale();
    const [values, setValues] = useState<Record<number, string>>(() =>
        Object.fromEntries([1, 2, 3, 4, 5, 6, 7].map((d) => [d, String(coverage[String(d)] ?? 0)])),
    );
    // 2024-01-01 was a Monday: ISO weekday d is 2024-01-0d.
    const weekday = (d: number) =>
        formatDate(new Date(Date.UTC(2024, 0, d)), locale, { weekday: 'long', timeZone: 'UTC' });

    return (
        <ResponsiveDialog
            open={open}
            onOpenChange={(next) => !next && onClose()}
            title={t('coverageDialog.title')}
            description={t('coverageDialog.help')}
            footer={
                <Button
                    type="button"
                    onClick={() =>
                        router.put(
                            roster.coverage.url(branchId),
                            {
                                required: Object.fromEntries(
                                    Object.entries(values).map(([d, v]) => [d, Number(v) || 0]),
                                ),
                            },
                            { preserveScroll: true, onSuccess: onClose },
                        )
                    }
                >
                    {t('coverageDialog.save')}
                </Button>
            }
        >
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                {[1, 2, 3, 4, 5, 6, 7].map((d) => (
                    <Field key={d} id={`coverage-${d}`} label={weekday(d)}>
                        <Input
                            id={`coverage-${d}`}
                            inputMode="numeric"
                            className="tabular-nums"
                            value={values[d] ?? '0'}
                            onChange={(e) => setValues({ ...values, [d]: e.target.value })}
                        />
                    </Field>
                ))}
            </div>
        </ResponsiveDialog>
    );
}
