import { useTranslation } from 'react-i18next';
import { UnderlineTabs } from '@/components/mw/underline-tabs';
import { useCurrentUrl } from '@/hooks/use-current-url';
import people from '@/routes/people';

export function PeopleSettingsTabs() {
    const { t } = useTranslation('people');
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <UnderlineTabs
            label={t('settings.tabs.label')}
            items={[
                {
                    key: 'fields',
                    label: t('settings.tabs.fields'),
                    href: people.settings.fields.url(),
                    active: isCurrentOrParentUrl(people.settings.fields()),
                },
                {
                    key: 'documents',
                    label: t('settings.tabs.documentTypes'),
                    href: people.settings.documentTypes.url(),
                    active: isCurrentOrParentUrl(people.settings.documentTypes()),
                },
            ]}
        />
    );
}
