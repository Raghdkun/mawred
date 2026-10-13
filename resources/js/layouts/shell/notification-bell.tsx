import { Link, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { useLocale } from '@/hooks/use-locale';
import { formatNumber } from '@/lib/format';
import notifications from '@/routes/notifications';

export function NotificationBell() {
    const { t } = useTranslation('shell');
    const { locale } = useLocale();
    const unread = usePage().props.notifications?.unread ?? 0;

    return (
        <Button
            variant="ghost"
            size="icon"
            asChild
            aria-label={t('notifications', { count: unread })}
        >
            <Link href={notifications.index()} prefetch className="relative">
                <Bell aria-hidden />
                {unread > 0 ? (
                    <span className="absolute -end-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full border-2 border-card bg-notification-dot px-1 text-[10px] leading-none font-semibold text-ink-950">
                        {formatNumber(Math.min(unread, 99), locale)}
                    </span>
                ) : null}
            </Link>
        </Button>
    );
}
