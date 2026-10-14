import { Link, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Facts } from '@/components/mw/facts';
import { Section } from '@/components/mw/page-header';
import { Button } from '@/components/ui/button';
import { useLocale } from '@/hooks/use-locale';
import { formatDate } from '@/lib/format';
import { localized, personName } from '@/lib/localized';
import people from '@/routes/people';
import { PersonAvatar } from '../person-avatar';
import { type ProfileProps } from './types';

export function OverviewTab({
    profile,
    directReports,
    can,
}: Pick<ProfileProps, 'profile' | 'directReports' | 'can'>) {
    const { t } = useTranslation('people');
    const { locale } = useLocale();
    const summary = profile.summary;
    const date = (value: string | null) =>
        value ? formatDate(value, locale, { dateStyle: 'medium' }) : '—';

    return (
        <div className="grid gap-6 lg:grid-cols-2">
            <Section title={t('sections.contact')}>
                <Facts
                    items={[
                        [
                            t('fields.workEmail'),
                            summary.workEmail ? <bdi dir="ltr">{summary.workEmail}</bdi> : '—',
                        ],
                        [
                            t('fields.workPhone'),
                            summary.workPhone ? <bdi dir="ltr">{summary.workPhone}</bdi> : '—',
                        ],
                    ]}
                />
            </Section>
            <Section title={t('sections.placement')}>
                <Facts
                    items={[
                        [
                            t('fields.branch'),
                            summary.branch ? localized(summary.branch.name, locale) : '—',
                        ],
                        [
                            t('fields.department'),
                            summary.department ? localized(summary.department.name, locale) : '—',
                        ],
                        [
                            t('fields.position'),
                            summary.position ? localized(summary.position.name, locale) : '—',
                        ],
                        [
                            t('fields.manager'),
                            summary.manager ? (
                                <Link
                                    href={people.show.url(summary.manager.id)}
                                    className="text-link hover:underline"
                                >
                                    {personName(summary.manager, locale)}
                                </Link>
                            ) : (
                                '—'
                            ),
                        ],
                        [
                            t('fields.employmentType'),
                            profile.employmentType
                                ? localized(profile.employmentType.name, locale)
                                : '—',
                        ],
                        [
                            t('fields.grade'),
                            profile.grade ? localized(profile.grade.name, locale) : '—',
                        ],
                    ]}
                />
            </Section>
            <Section title={t('sections.employment')}>
                <Facts
                    items={[
                        [t('fields.hireDate'), date(summary.hireDate)],
                        [t('fields.probationEndsOn'), date(profile.probationEndsOn)],
                        ...(profile.terminationDate
                            ? ([
                                  [t('fields.lastDay'), date(profile.terminationDate)],
                                  [t('fields.terminationReason'), profile.terminationReason],
                              ] as Array<[string, React.ReactNode]>)
                            : []),
                    ]}
                />
            </Section>
            <Section title={t('sections.account')}>
                {profile.account.linked ? (
                    <p className="text-sm">
                        {t('account.linked')}{' '}
                        <bdi dir="ltr" className="text-text-secondary">
                            {profile.account.email}
                        </bdi>
                    </p>
                ) : profile.account.invitation ? (
                    <p className="text-sm">
                        {t('account.invited', {
                            email: profile.account.invitation.email,
                            date: formatDate(profile.account.invitation.expiresAt, locale, {
                                dateStyle: 'medium',
                            }),
                        })}
                    </p>
                ) : (
                    <div className="flex flex-wrap items-center gap-3">
                        <p className="text-sm text-text-secondary">{t('account.none')}</p>
                        {can.invite ? (
                            <Button
                                size="sm"
                                onClick={() =>
                                    router.post(
                                        people.invitation.store.url(summary.id),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {t('profile.invite')}
                            </Button>
                        ) : null}
                    </div>
                )}
            </Section>
            {profile.directReportsCount > 0 ? (
                <Section
                    title={t('sections.directReports', { count: profile.directReportsCount })}
                    className="lg:col-span-2"
                >
                    <ul className="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                        {directReports.map((report) => (
                            <li key={report.id}>
                                <Link
                                    href={people.show.url(report.id)}
                                    className="flex items-center gap-3 rounded-md p-2 hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    <PersonAvatar
                                        name={personName(report, locale)}
                                        className="size-8"
                                    />
                                    <span className="flex min-w-0 flex-col">
                                        <span className="truncate text-sm font-medium">
                                            {personName(report, locale)}
                                        </span>
                                        <span className="truncate text-caption text-muted-foreground">
                                            {report.position
                                                ? localized(report.position.name, locale)
                                                : report.number}
                                        </span>
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </Section>
            ) : null}
        </div>
    );
}
