import { useTranslation } from 'react-i18next';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useLocale } from '@/hooks/use-locale';
import { cn } from '@/lib/utils';
import { formatValue, isNumeric } from './format';

type Result = App.Domain.Reports.Data.ReportResultData;

/**
 * The report's rows (handoff 36): a table from `md` up, cards below it. Numbers line up at the
 * cell's end and use tabular figures; text is isolated so mixed scripts read correctly.
 */
export function ReportTable({ result, title }: { result: Result; title: string }) {
    const { t } = useTranslation('reports');
    const { locale } = useLocale();
    // Cards lead with the person's (or row's) name when there is one, else the first column.
    const first = result.columns.find((column) => column.key === 'name') ?? result.columns[0];
    const rest = result.columns.filter((column) => column !== first);

    return (
        <section aria-label={t('result.table', { title })} className="flex flex-col gap-2">
            <div className="hidden overflow-x-auto rounded-lg border border-border bg-card md:block">
                <Table data-report-table>
                    <TableHeader>
                        <TableRow>
                            {result.columns.map((column) => (
                                <TableHead
                                    key={column.key}
                                    scope="col"
                                    className={cn(
                                        'whitespace-nowrap',
                                        isNumeric(column.type) && 'text-end',
                                    )}
                                >
                                    {column.label}
                                </TableHead>
                            ))}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {result.rows.map((row, index) => (
                            <TableRow key={index}>
                                {result.columns.map((column) => (
                                    <TableCell
                                        key={column.key}
                                        className={cn(
                                            isNumeric(column.type) && 'text-end tabular-nums',
                                            column.type === 'text' && 'max-w-72 whitespace-normal',
                                        )}
                                    >
                                        <bdi>
                                            {formatValue(row[column.key], column.type, locale, t)}
                                        </bdi>
                                    </TableCell>
                                ))}
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
            <ul className="flex flex-col gap-2 md:hidden" data-report-cards>
                {result.rows.map((row, index) => (
                    <li key={index} className="rounded-lg border border-border bg-card p-3">
                        {first ? (
                            <p className="mb-2 text-body font-medium">
                                <bdi>{formatValue(row[first.key], first.type, locale, t)}</bdi>
                            </p>
                        ) : null}
                        <dl className="grid grid-cols-2 gap-x-3 gap-y-1 text-small">
                            {rest.map((column) => (
                                <div key={column.key} className="flex flex-col">
                                    <dt className="text-caption text-text-secondary">
                                        {column.label}
                                    </dt>
                                    <dd className="tabular-nums">
                                        <bdi>
                                            {formatValue(row[column.key], column.type, locale, t)}
                                        </bdi>
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </li>
                ))}
            </ul>
        </section>
    );
}
