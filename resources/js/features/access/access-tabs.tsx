import { useTranslation } from 'react-i18next';
import { UnderlineTabs } from '@/components/mw/underline-tabs';
import { useCurrentUrl } from '@/hooks/use-current-url';
import accessRoles from '@/routes/access/roles';
import accessUsers from '@/routes/access/users';

export function AccessTabs() {
    const { t } = useTranslation('access');
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <UnderlineTabs
            label={t('tabs.label')}
            items={[
                {
                    key: 'roles',
                    label: t('tabs.roles'),
                    href: accessRoles.index.url(),
                    active: isCurrentOrParentUrl(accessRoles.index()),
                },
                {
                    key: 'users',
                    label: t('tabs.users'),
                    href: accessUsers.index.url(),
                    active: isCurrentOrParentUrl(accessUsers.index()),
                },
            ]}
        />
    );
}
