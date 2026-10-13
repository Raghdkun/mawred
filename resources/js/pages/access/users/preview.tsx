import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Eye, ShieldCheck } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { DirIcon } from '@/components/mw/dir-icon';
import { PageHeader, Section } from '@/components/mw/page-header';
import { Badge } from '@/components/ui/badge';
import { type CellValue } from '@/features/access/scopes';
import { useLocale } from '@/hooks/use-locale';
import accessUsers from '@/routes/access/users';

type Props = {
    user: App.Domain.Access.Data.UserAccountData;
    isSuperAdmin: boolean;
    requiresTwoFactor: boolean;
    homeLayout: string;
    matrix: Record<string, Record<string, string>>;
    fields: Record<string, 'read' | 'write' | null>;
    samples: { auditEntries: number | null };
};

/** "View as user" (handoff 38): a read-only preview, never a session switch. */
export default function UserPreview({
    user,
    isSuperAdmin,
    requiresTwoFactor,
    homeLayout,
    matrix,
    fields,
    samples,
}: Props) {
    const { t } = useTranslation('access');
    const { locale } = useLocale();
    const modules = Object.keys(matrix);

    return (
        <>
            <Head title={t('preview.pageTitle', { name: user.name })} />
            <Link
                href={accessUsers.index()}
                className="mb-4 inline-flex items-center gap-1.5 text-small text-link hover:underline"
            >
                <DirIcon>
                    <ArrowLeft aria-hidden className="size-4" />
                </DirIcon>
                {t('preview.back')}
            </Link>
            <PageHeader
                title={t('preview.title', { name: user.name })}
                description={t('preview.description')}
            />

            <div className="mb-6 flex flex-wrap gap-2">
                {user.roles.map((role) => (
                    <Badge key={role.name} variant="secondary">
                        {role.label[locale] ?? role.label.en ?? role.name}
                    </Badge>
                ))}
                <Badge variant="outline">
                    {t('preview.home', { layout: t(`details.homeLayouts.${homeLayout}`) })}
                </Badge>
                {requiresTwoFactor ? (
                    <Badge variant="outline" className="gap-1">
                        <ShieldCheck aria-hidden className="size-3" />
                        {t('preview.twoFactorRequired')}
                    </Badge>
                ) : null}
            </div>

            <div className="grid gap-6 lg:grid-cols-12">
                <Section title={t('preview.permissions')} className="lg:col-span-8">
                    {modules.length === 0 ? (
                        <p className="text-small text-muted-foreground">{t('preview.none')}</p>
                    ) : (
                        <ul className="flex flex-col divide-y divide-border">
                            {modules.map((module) => (
                                <li
                                    key={module}
                                    className="flex flex-col gap-2 py-3 sm:flex-row sm:items-start"
                                >
                                    <span className="text-sm font-medium sm:w-44">
                                        {t(`modules.${module}`)}
                                    </span>
                                    <div className="flex flex-1 flex-wrap gap-1.5">
                                        {Object.entries(matrix[module] ?? {}).map(
                                            ([action, scope]) => (
                                                <Badge
                                                    key={action}
                                                    variant="outline"
                                                    className="font-normal"
                                                >
                                                    {t(`actions.${action}`)} ·{' '}
                                                    {isSuperAdmin
                                                        ? t('scopeNames.all')
                                                        : t(`scopeNames.${scope as CellValue}`)}
                                                </Badge>
                                            ),
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Section>

                <div className="flex flex-col gap-6 lg:col-span-4">
                    <Section title={t('preview.fields')}>
                        <dl className="grid grid-cols-[minmax(0,1fr)_auto] gap-x-3 gap-y-2 text-small">
                            {Object.entries(fields).map(([field, access]) => (
                                <div key={field} className="contents">
                                    <dt className="text-text-secondary">
                                        {t(`fields.names.${field}`, { defaultValue: field })}
                                    </dt>
                                    <dd
                                        className={access ? 'font-medium' : 'text-muted-foreground'}
                                    >
                                        {t(`fields.${access ?? 'none'}`)}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </Section>
                    <Section title={t('preview.visibility')}>
                        <p className="flex items-center gap-2 text-small">
                            <Eye aria-hidden className="size-4 text-muted-foreground" />
                            {samples.auditEntries === null
                                ? t('preview.noAudit')
                                : t('preview.auditEntries', { count: samples.auditEntries })}
                        </p>
                    </Section>
                </div>
            </div>
        </>
    );
}
