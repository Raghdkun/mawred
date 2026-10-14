import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { useLocale } from '@/hooks/use-locale';
import { formatNumber } from '@/lib/format';

type FormData = App.Domain.Forms.Data.FormData;

/** Draft (never published), Published vN, or Published vN with unpublished changes. */
export function FormStatusBadge({ form }: { form: FormData }) {
    const { t } = useTranslation('forms');
    const { locale } = useLocale();
    const version = form.publishedNumber === null ? '' : formatNumber(form.publishedNumber, locale);

    if (form.status === 'draft') {
        return <Badge variant="outline">{t('status.draft')}</Badge>;
    }

    return (
        <span className="flex flex-wrap items-center gap-1.5">
            <Badge variant="secondary">{t('status.published', { version })}</Badge>
            {form.status === 'changes' ? (
                <Badge variant="outline">{t('status.changes')}</Badge>
            ) : null}
        </span>
    );
}
