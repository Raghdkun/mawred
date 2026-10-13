import type { PropsWithChildren } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/mw/page-header';
import { UnderlineTabs } from '@/components/mw/underline-tabs';
import { useCurrentUrl } from '@/hooks/use-current-url';
import preferences from '@/routes/preferences';
import profile from '@/routes/profile';
import security from '@/routes/security';

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { t } = useTranslation('settings');
    const { isCurrentOrParentUrl } = useCurrentUrl();

    const items = [
        { key: 'profile', label: t('tabs.profile'), route: profile.edit() },
        { key: 'security', label: t('tabs.security'), route: security.edit() },
        { key: 'preferences', label: t('tabs.preferences'), route: preferences.edit() },
    ];

    return (
        <>
            <PageHeader title={t('title')} description={t('description')} />
            <UnderlineTabs
                label={t('tabs.label')}
                items={items.map((item) => ({
                    key: item.key,
                    label: item.label,
                    href: item.route.url,
                    active: isCurrentOrParentUrl(item.route),
                }))}
            />
            <div className="flex max-w-3xl flex-col gap-6">{children}</div>
        </>
    );
}
