import { useTranslation } from 'react-i18next';
import { UnderlineTabs } from '@/components/mw/underline-tabs';
import { useCurrentUrl } from '@/hooks/use-current-url';
import policies from '@/routes/policies';
import assignments from '@/routes/policies/assignments';
import holidays from '@/routes/policies/holidays';
import leaveTypes from '@/routes/policies/leave-types';

/** Policies sections (screens 16, 18, 19, 21 and the leave type catalogue). */
export function PoliciesTabs() {
    const { t } = useTranslation('policies');
    const { currentUrl } = useCurrentUrl();
    const under = (url: string) =>
        currentUrl === url || currentUrl.startsWith(`${url}/`) || currentUrl.startsWith(`${url}?`);
    const fixed = [
        assignments.index.url(),
        policies.effective.url(),
        leaveTypes.index.url(),
        holidays.index.url(),
    ];
    const inSets = under(policies.index.url()) && !fixed.some(under);

    return (
        <UnderlineTabs
            label={t('tabs.label')}
            items={[
                { key: 'sets', label: t('tabs.sets'), href: policies.index.url(), active: inSets },
                {
                    key: 'assignments',
                    label: t('tabs.assignments'),
                    href: assignments.index.url(),
                    active: under(assignments.index.url()),
                },
                {
                    key: 'effective',
                    label: t('tabs.effective'),
                    href: policies.effective.url(),
                    active: under(policies.effective.url()),
                },
                {
                    key: 'leave-types',
                    label: t('tabs.leaveTypes'),
                    href: leaveTypes.index.url(),
                    active: under(leaveTypes.index.url()),
                },
                {
                    key: 'holidays',
                    label: t('tabs.holidays'),
                    href: holidays.index.url(),
                    active: under(holidays.index.url()),
                },
            ]}
        />
    );
}
