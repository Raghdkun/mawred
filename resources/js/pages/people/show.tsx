import { Head, Link } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { DirIcon } from '@/components/mw/dir-icon';
import { DocumentsTab } from '@/features/people/profile/documents-tab';
import { HistoryTab } from '@/features/people/profile/history-tab';
import { JobTab } from '@/features/people/profile/job-tab';
import { OverviewTab } from '@/features/people/profile/overview-tab';
import { PersonalTab } from '@/features/people/profile/personal-tab';
import { ProfileHeader } from '@/features/people/profile/profile-header';
import { type ProfileProps } from '@/features/people/profile/types';
import { useLocale } from '@/hooks/use-locale';
import { personName } from '@/lib/localized';
import { cn } from '@/lib/utils';
import people from '@/routes/people';

/**
 * Leave, Attendance, Training and Effective policies tabs join with their modules (Phases 2–6);
 * nothing links to an unbuilt tab.
 */
const TABS = ['overview', 'job', 'personal', 'documents', 'history'] as const;
type Tab = (typeof TABS)[number];

/** Employee profile (handoff 8): header + tabs; tabs scroll horizontally on phones. */
export default function PeopleShow(props: ProfileProps) {
    const { t } = useTranslation('people');
    const { locale } = useLocale();
    const [tab, setTab] = useState<Tab>(
        TABS.includes(props.tab as Tab) ? (props.tab as Tab) : 'overview',
    );

    const select = (next: Tab) => {
        setTab(next);
        const url = new URL(window.location.href);
        if (next === 'overview') {
            url.searchParams.delete('tab');
        } else {
            url.searchParams.set('tab', next);
        }
        window.history.replaceState(window.history.state, '', url);
    };

    return (
        <>
            <Head title={personName(props.profile.summary, locale)} />
            <Link
                href={people.index.url()}
                className="mb-4 inline-flex items-center gap-1.5 text-small text-link hover:underline"
            >
                <DirIcon>
                    <ChevronLeft aria-hidden className="size-4" />
                </DirIcon>
                {t('profile.back')}
            </Link>
            <ProfileHeader profile={props.profile} can={props.can} />

            <div
                role="tablist"
                aria-label={t('profile.tabs.label')}
                className="-mx-4 mb-6 overflow-x-auto px-4 md:mx-0 md:px-0"
            >
                <div className="flex min-w-max gap-1 border-b border-border">
                    {TABS.map((key) => (
                        <button
                            key={key}
                            type="button"
                            role="tab"
                            id={`tab-${key}`}
                            aria-selected={tab === key}
                            aria-controls={`panel-${key}`}
                            onClick={() => select(key)}
                            className={cn(
                                'relative flex h-11 items-center gap-2 rounded-t-sm px-3.5 text-sm text-text-secondary transition-colors duration-150 ease-soft hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                tab === key &&
                                    'font-medium text-foreground after:absolute after:inset-x-2.5 after:-bottom-px after:h-0.5 after:rounded-full after:bg-primary hover:bg-transparent',
                            )}
                        >
                            {t(`profile.tabs.${key}`)}
                            {key === 'documents' && props.documents.length ? (
                                <span className="rounded-full bg-muted px-1.5 text-caption">
                                    {props.documents.length}
                                </span>
                            ) : null}
                        </button>
                    ))}
                </div>
            </div>

            <div role="tabpanel" id={`panel-${tab}`} aria-labelledby={`tab-${tab}`}>
                {tab === 'overview' ? <OverviewTab {...props} /> : null}
                {tab === 'job' ? <JobTab {...props} /> : null}
                {tab === 'personal' ? <PersonalTab {...props} /> : null}
                {tab === 'documents' ? <DocumentsTab {...props} /> : null}
                {tab === 'history' ? <HistoryTab {...props} /> : null}
            </div>
        </>
    );
}
