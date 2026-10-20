import { Link } from '@inertiajs/react';
import { LogIn, LogOut } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { useLocale } from '@/hooks/use-locale';
import { formatDate } from '@/lib/format';
import attendance from '@/routes/attendance';

type Clock = NonNullable<App.Domain.Reports.Data.EmployeeDashboardData['clock']>;

/** The check-in card (handoff 4): today's status and shift, and the way to check in or out. */
export function ClockCard({ clock }: { clock: Clock }) {
    const { t } = useTranslation('home');
    const { locale } = useLocale();
    const time = (iso: string) =>
        formatDate(iso, locale, { hour: 'numeric', minute: '2-digit', timeZone: clock.timezone });

    return (
        <section
            aria-labelledby="clock-card"
            className="flex flex-col gap-3 rounded-lg border border-border bg-card p-5 shadow-sm"
            data-clock-card
        >
            <div className="flex items-center justify-between gap-2">
                <h2 id="clock-card" className="text-h4">
                    {t('clock.title')}
                </h2>
                {clock.status ? (
                    <span className="text-small text-text-secondary">
                        {t(`clock.status.${clock.status}`)}
                    </span>
                ) : null}
            </div>
            <p className="text-body">
                {clock.lastOut ? (
                    <bdi>{t('clock.out', { time: time(clock.lastOut) })}</bdi>
                ) : clock.firstIn ? (
                    <bdi>{t('clock.in', { time: time(clock.firstIn) })}</bdi>
                ) : (
                    t('clock.notIn')
                )}
            </p>
            <p className="text-small text-text-secondary">
                {clock.shift ? (
                    <bdi>
                        {t('clock.shift', {
                            start: time(clock.shift.startsAt),
                            end: time(clock.shift.endsAt),
                        })}
                    </bdi>
                ) : (
                    t('clock.noShift')
                )}
            </p>
            <Button asChild className="min-h-11 self-start">
                <Link href={attendance.clock.url()} data-clock-action={clock.next}>
                    {clock.next === 'out' ? <LogOut aria-hidden /> : <LogIn aria-hidden />}
                    {clock.next === 'out' ? t('clock.checkOut') : t('clock.checkIn')}
                </Link>
            </Button>
        </section>
    );
}
