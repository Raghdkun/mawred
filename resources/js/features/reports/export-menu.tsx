import { router } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useLocale } from '@/hooks/use-locale';
import reports from '@/routes/reports';
import { type FilterValues } from './filter-bar';

/**
 * Export (D113): the file is made on the server with the filters on screen, and arrives in My
 * exports with a notification when it's ready.
 */
export function ExportMenu({
    reportKey,
    values,
    formats,
}: {
    reportKey: string;
    values: FilterValues;
    formats: App.Domain.Reports.Enums.ReportFormat[];
}) {
    const { t } = useTranslation('reports');
    const { dir } = useLocale();
    const [sending, setSending] = useState(false);

    const request = (format: string) => {
        const data: Record<string, string> = { format };

        for (const [key, value] of Object.entries(values)) {
            if (value !== null && value !== '') {
                data[key] = String(value);
            }
        }

        setSending(true);
        router.post(reports.exports.store.url(reportKey), data, {
            onFinish: () => setSending(false),
        });
    };

    return (
        <DropdownMenu dir={dir}>
            <DropdownMenuTrigger asChild>
                <Button className="min-h-11" disabled={sending} data-export>
                    <Download aria-hidden />
                    {t('export.button')}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuLabel>{t('export.label')}</DropdownMenuLabel>
                {formats.map((format) => (
                    <DropdownMenuItem
                        key={format}
                        onSelect={() => request(format)}
                        data-export-format={format}
                    >
                        {t(`export.${format}`)}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
