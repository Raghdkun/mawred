import { Head, Link } from '@inertiajs/react';
import { CalendarDays, Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { Button } from '@/components/ui/button';
import { LeavePanel } from '@/features/leave/leave-panel';
import leave from '@/routes/leave';
import requests from '@/routes/requests';

type Props = {
    employeeId: string | null;
    year: number;
    can: { configure: boolean };
};

/** My leave: balances for the leave year (D63), statements and my time away. */
export default function MyLeave({ employeeId, year, can }: Props) {
    const { t } = useTranslation('leave');

    return (
        <>
            <Head title={t('pageTitle')} />
            <PageHeader
                title={t('pageTitle')}
                description={t('description')}
                actions={
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline" className="min-h-11">
                            <Link href={leave.calendar.url()}>
                                <CalendarDays aria-hidden className="size-4" />
                                {t('links.calendar')}
                            </Link>
                        </Button>
                        {can.configure ? (
                            <Button asChild variant="outline" className="min-h-11">
                                <Link href={leave.blackouts.index.url()}>
                                    {t('links.blackouts')}
                                </Link>
                            </Button>
                        ) : null}
                        {employeeId ? (
                            <Button asChild className="min-h-11">
                                <Link href={requests.center.url()}>
                                    <Plus aria-hidden className="size-4" />
                                    {t('requestLeave')}
                                </Link>
                            </Button>
                        ) : null}
                    </div>
                }
            />
            {employeeId ? (
                <LeavePanel employeeId={employeeId} initialYear={year} />
            ) : (
                <PageState
                    kind="empty"
                    illustration="empty-employees"
                    title={t('noEmployee.title')}
                    description={t('noEmployee.description')}
                />
            )}
        </>
    );
}
