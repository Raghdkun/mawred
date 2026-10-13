import { Head, Link } from '@inertiajs/react';
import { ChevronRight, KeyRound, ShieldCheck } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { DirIcon } from '@/components/mw/dir-icon';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { Badge } from '@/components/ui/badge';
import { AccessTabs } from '@/features/access/access-tabs';
import { useLocale } from '@/hooks/use-locale';
import accessRoles from '@/routes/access/roles';

type Role = App.Domain.Access.Data.RoleSummaryData;

export default function RolesIndex({ roles }: { roles: Role[] }) {
    const { t } = useTranslation('access');
    const { locale } = useLocale();

    return (
        <>
            <Head title={t('roles.pageTitle')} />
            <PageHeader title={t('title')} description={t('description')} />
            <AccessTabs />

            {roles.length === 0 ? (
                <PageState kind="empty" illustration="empty-policies" title={t('roles.empty')} />
            ) : (
                <ul className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    {roles.map((role) => (
                        <li key={role.id}>
                            <Link
                                href={accessRoles.show(role.id)}
                                prefetch
                                className="group flex h-full flex-col gap-3 rounded-lg bg-card p-5 shadow-sm inset-shadow-highlight transition-shadow duration-150 ease-soft hover:shadow-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <h2 className="text-h4 text-foreground">
                                        {role.label[locale] ?? role.label.en ?? role.name}
                                    </h2>
                                    <DirIcon>
                                        <ChevronRight
                                            aria-hidden
                                            className="mt-1 size-4 text-muted-foreground transition-transform duration-150 group-hover:translate-x-0.5 rtl:group-hover:-translate-x-0.5"
                                        />
                                    </DirIcon>
                                </div>
                                <p className="line-clamp-2 text-small text-muted-foreground">
                                    {role.description[locale] ?? role.description.en}
                                </p>
                                <div className="mt-auto flex flex-wrap items-center gap-2 text-caption text-text-secondary">
                                    <span>{t('roles.users', { count: role.usersCount })}</span>
                                    <span aria-hidden>·</span>
                                    <span>
                                        {role.isSuperAdmin
                                            ? t('roles.superAdmin')
                                            : t('roles.permissions', {
                                                  count: role.permissionsCount,
                                              })}
                                    </span>
                                </div>
                                <div className="flex flex-wrap gap-1.5">
                                    {role.isSystem ? (
                                        <Badge variant="secondary">{t('roles.system')}</Badge>
                                    ) : null}
                                    {role.requiresTwoFactor ? (
                                        <Badge variant="outline" className="gap-1">
                                            <ShieldCheck aria-hidden className="size-3" />
                                            {t('roles.twoFactor')}
                                        </Badge>
                                    ) : null}
                                    {role.apiOnly ? (
                                        <Badge variant="outline" className="gap-1">
                                            <KeyRound aria-hidden className="size-3" />
                                            {t('roles.apiOnly')}
                                        </Badge>
                                    ) : null}
                                </div>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </>
    );
}
