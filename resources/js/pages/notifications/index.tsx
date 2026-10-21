import { Head, Link, router } from '@inertiajs/react';
import { CheckCheck, Settings2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { UnderlineTabs } from '@/components/mw/underline-tabs';
import { Button } from '@/components/ui/button';
import { useLocale } from '@/hooks/use-locale';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import notifications from '@/routes/notifications';

type Item = App.Domain.Notifications.Data.NotificationItemData;

/** Notification center (handoff 44); preferences live on their own page. */
export default function NotificationsIndex({
    notifications: page,
    filter,
}: {
    notifications: { data: Item[]; nextCursor: string | null };
    filter: 'all' | 'unread';
}) {
    const { t } = useTranslation('notifications');
    const { locale } = useLocale();

    return (
        <>
            <Head title={t('pageTitle')} />
            <PageHeader
                title={t('title')}
                description={t('description')}
                actions={
                    <>
                        {page.data.some((item) => !item.read) ? (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.post(
                                        notifications.readAll.url(),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <CheckCheck aria-hidden /> {t('markAllRead')}
                            </Button>
                        ) : null}
                        <Button variant="ghost" asChild>
                            <Link href={notifications.preferences.url()}>
                                <Settings2 aria-hidden /> {t('preferences.link')}
                            </Link>
                        </Button>
                    </>
                }
            />
            <UnderlineTabs
                label={t('filters.label')}
                items={[
                    {
                        key: 'all',
                        label: t('filters.all'),
                        href: notifications.index.url(),
                        active: filter === 'all',
                    },
                    {
                        key: 'unread',
                        label: t('filters.unread'),
                        href: notifications.index.url({ query: { filter: 'unread' } }),
                        active: filter === 'unread',
                    },
                ]}
            />
            {page.data.length === 0 ? (
                <PageState
                    kind="empty"
                    illustration="empty-inbox"
                    title={t('empty.title')}
                    description={t('empty.description')}
                />
            ) : (
                <ul className="overflow-hidden rounded-lg border border-border bg-card">
                    {page.data.map((item) => (
                        <li
                            key={item.id}
                            className={cn(
                                'flex items-start gap-3 border-b border-border p-4 last:border-b-0',
                                !item.read && 'bg-selected/40',
                            )}
                        >
                            <span
                                aria-hidden
                                className={cn(
                                    'mt-2 size-2 shrink-0 rounded-full',
                                    item.read ? 'bg-transparent' : 'bg-primary',
                                )}
                            />
                            <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                                {item.url ? (
                                    <Link
                                        href={item.url}
                                        onClick={() =>
                                            !item.read &&
                                            router.post(
                                                notifications.read.url(item.id),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                        className="text-sm font-medium hover:underline"
                                    >
                                        {item.title}
                                    </Link>
                                ) : (
                                    <span className="text-sm font-medium">{item.title}</span>
                                )}
                                {item.body ? (
                                    <p className="text-small text-text-secondary">{item.body}</p>
                                ) : null}
                                <time
                                    dateTime={item.createdAt}
                                    className="text-caption text-text-secondary"
                                >
                                    {formatDate(item.createdAt, locale, {
                                        dateStyle: 'medium',
                                        timeStyle: 'short',
                                    })}
                                </time>
                            </div>
                            {!item.read ? <span className="sr-only">{t('unread')}</span> : null}
                        </li>
                    ))}
                </ul>
            )}
        </>
    );
}
