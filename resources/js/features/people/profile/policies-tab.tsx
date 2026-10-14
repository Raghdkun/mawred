import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Field } from '@/components/mw/field';
import { PageState } from '@/components/mw/page-state';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { ExplainerChip } from '@/features/policies/explainer';
import { type PolicyKey, type ResolvedValue } from '@/features/policies/types';
import { SECTIONS, keyLabel, summarize } from '@/features/policies/values';
import { useLocale } from '@/hooks/use-locale';
import { api } from '@/lib/api';
import { type ProfileProps } from './types';

type Response = { data: ResolvedValue[]; meta: { date: string; keys: PolicyKey[] } };

/** Effective policies tab (handoff 8 + 19): every rule for this person on a date, with its source. */
export function PoliciesTab({ profile }: ProfileProps) {
    const { t } = useTranslation('policies');
    const { locale } = useLocale();
    const [date, setDate] = useState(() => new Date().toISOString().slice(0, 10));
    const query = useQuery({
        queryKey: ['employee-policies', profile.summary.id, date],
        queryFn: ({ signal }) =>
            api<Response>(`employees/${profile.summary.id}/policies`, { params: { date }, signal }),
        staleTime: 30_000,
    });

    return (
        <div className="flex flex-col gap-4">
            <Field id="profile-policy-date" label={t('effective.date')}>
                <Input
                    id="profile-policy-date"
                    type="date"
                    dir="ltr"
                    className="w-44"
                    value={date}
                    onChange={(event) => event.target.value && setDate(event.target.value)}
                />
            </Field>
            {query.isPending ? (
                <div aria-busy className="flex flex-col gap-2">
                    <Skeleton className="h-12 w-full" />
                    <Skeleton className="h-12 w-full" />
                    <Skeleton className="h-12 w-full" />
                </div>
            ) : query.isError ? (
                <PageState kind="error" />
            ) : (
                SECTIONS.map((section) => {
                    const rows = query.data.data.filter((value) => value.section === section);
                    return rows.length === 0 ? null : (
                        <section
                            key={section}
                            aria-labelledby={`profile-policies-${section}`}
                            className="flex flex-col gap-2"
                        >
                            <h3
                                id={`profile-policies-${section}`}
                                className="text-body font-semibold"
                            >
                                {t(`sections.${section}`)}
                            </h3>
                            <ul className="flex flex-col overflow-hidden rounded-lg border border-border bg-card">
                                {rows.map((row) => {
                                    const key = query.data.meta.keys.find(
                                        (candidate) => candidate.id === row.key,
                                    );
                                    const editor = key?.editor ?? '';

                                    return (
                                        <li
                                            key={row.key}
                                            className="grid gap-2 border-b border-border px-4 py-3 last:border-b-0 md:grid-cols-[minmax(0,1.2fr)_minmax(0,1.5fr)_minmax(0,1fr)] md:items-center md:gap-4"
                                        >
                                            <span className="text-sm font-medium">
                                                {key ? keyLabel(key, t, locale) : row.key}
                                            </span>
                                            <span className="text-small tabular-nums">
                                                {summarize(editor, row.value, t, locale)}
                                            </span>
                                            <span className="min-w-0">
                                                <ExplainerChip resolved={row} editor={editor} />
                                            </span>
                                        </li>
                                    );
                                })}
                            </ul>
                        </section>
                    );
                })
            )}
        </div>
    );
}
