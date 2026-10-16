import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { SuccessRipple } from '@/components/mw/success-ripple';
import { useLocale } from '@/hooks/use-locale';
import { formatDate } from '@/lib/format';

type Props = { title: string; startsAt: string; timezone: string; response: 'accepted' | 'tentative' | 'declined' };

/** After a one-tap reply from an email (D75): what was saved, and that it can be changed. */
export default function CalendarReply({ title, startsAt, timezone, response }: Props) {
    const { t } = useTranslation('calendar');
    const { locale } = useLocale();

    return (
        <>
            <Head title={t('reply.title')} />
            <div className="mx-auto flex w-full max-w-md flex-1 flex-col items-center justify-center gap-4 p-6 text-center">
                <SuccessRipple label={t('reply.title')} />
                <h1 className="text-h2">{t('reply.title')}</h1>
                <p className="text-body">{t(`reply.${response}`, { title })}</p>
                <p className="text-small text-text-secondary">{formatDate(startsAt, locale, { dateStyle: 'full', timeStyle: 'short', timeZone: timezone })}</p>
                <p className="text-small text-text-secondary">{t('reply.change')}</p>
            </div>
        </>
    );
}
