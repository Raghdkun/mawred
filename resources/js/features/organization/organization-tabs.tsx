import { useTranslation } from 'react-i18next';
import { UnderlineTabs } from '@/components/mw/underline-tabs';
import { useCurrentUrl } from '@/hooks/use-current-url';
import branches from '@/routes/organization/branches';
import catalogs from '@/routes/organization/catalogs';
import departments from '@/routes/organization/departments';
import positions from '@/routes/organization/positions';

/** Screen 39 sections: Branches, Departments, Positions, Types & grades. */
export function OrganizationTabs() {
    const { t } = useTranslation('organization');
    const { currentUrl, isCurrentOrParentUrl } = useCurrentUrl();
    const inCatalogs = ['employment-types', 'grades', 'cost-centers'].some((kind) =>
        currentUrl.startsWith(`/organization/${kind}`),
    );

    return (
        <UnderlineTabs
            label={t('tabs.label')}
            items={[
                {
                    key: 'branches',
                    label: t('tabs.branches'),
                    href: branches.index.url(),
                    active: isCurrentOrParentUrl(branches.index()),
                },
                {
                    key: 'departments',
                    label: t('tabs.departments'),
                    href: departments.index.url(),
                    active: isCurrentOrParentUrl(departments.index()),
                },
                {
                    key: 'positions',
                    label: t('tabs.positions'),
                    href: positions.index.url(),
                    active: isCurrentOrParentUrl(positions.index()),
                },
                {
                    key: 'catalogs',
                    label: t('tabs.catalogs'),
                    href: catalogs.index.url('employment-types'),
                    active: inCatalogs,
                },
            ]}
        />
    );
}
