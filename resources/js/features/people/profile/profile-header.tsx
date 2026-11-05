import { router } from '@inertiajs/react';
import { MoreHorizontal } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Field } from '@/components/mw/field';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useLocale } from '@/hooks/use-locale';
import { localized, personName } from '@/lib/localized';
import people from '@/routes/people';
import { EmployeeStatusBadge } from '../employee-status-badge';
import { PersonAvatar } from '../person-avatar';
import { InvitationLinkDialog } from './invitation-link-dialog';
import { type ProfileProps, today } from './types';

/** Profile header (screen 8): avatar, name, role line, status and the record-level actions. */
export function ProfileHeader({ profile, can }: Pick<ProfileProps, 'profile' | 'can'>) {
    const { t } = useTranslation('people');
    const { locale } = useLocale();
    const [terminating, setTerminating] = useState(false);
    const summary = profile.summary;
    const name = personName(summary, locale);
    const role = [
        summary.position ? localized(summary.position.name, locale) : null,
        summary.department ? localized(summary.department.name, locale) : null,
        summary.branch ? localized(summary.branch.name, locale) : null,
    ].filter(Boolean);

    return (
        <header className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center">
            <PersonAvatar name={name} className="size-16 text-h4" />
            <div className="flex min-w-0 flex-1 flex-col gap-1">
                <div className="flex flex-wrap items-center gap-2">
                    <h1 className="text-h2 text-foreground">{name}</h1>
                    <EmployeeStatusBadge status={summary.status} />
                </div>
                {locale === 'ar' && summary.nameEn !== name ? (
                    <bdi dir="ltr" lang="en" className="text-small text-muted-foreground">
                        {summary.nameEn}
                    </bdi>
                ) : null}
                {locale !== 'ar' && summary.nameAr ? (
                    <bdi dir="rtl" lang="ar" className="text-small text-muted-foreground">
                        {summary.nameAr}
                    </bdi>
                ) : null}
                <p className="text-small text-text-secondary">
                    <bdi dir="ltr">{summary.number}</bdi>
                    {role.length ? ` · ${role.join(' · ')}` : ''}
                </p>
            </div>
            {can.invite || can.terminate || profile.account.invitation ? (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" aria-label={t('profile.actions')}>
                            <MoreHorizontal aria-hidden />
                            <span className="sm:hidden">{t('profile.actions')}</span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        {can.invite ? (
                            <DropdownMenuItem
                                onSelect={() =>
                                    router.post(
                                        people.invitation.store.url(summary.id),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {profile.account.invitation
                                    ? t('profile.resendInvite')
                                    : t('profile.invite')}
                            </DropdownMenuItem>
                        ) : null}
                        {can.update && profile.account.invitation ? (
                            <DropdownMenuItem
                                onSelect={() =>
                                    router.delete(people.invitation.destroy.url(summary.id), {
                                        preserveScroll: true,
                                    })
                                }
                            >
                                {t('profile.cancelInvite')}
                            </DropdownMenuItem>
                        ) : null}
                        {can.terminate ? (
                            <DropdownMenuItem
                                variant="destructive"
                                onSelect={() => setTerminating(true)}
                            >
                                {t('profile.terminate')}
                            </DropdownMenuItem>
                        ) : null}
                    </DropdownMenuContent>
                </DropdownMenu>
            ) : null}
            {terminating ? (
                <TerminateDialog
                    employeeId={summary.id}
                    name={name}
                    onClose={() => setTerminating(false)}
                />
            ) : null}
            <InvitationLinkDialog />
        </header>
    );
}

function TerminateDialog({
    employeeId,
    name,
    onClose,
}: {
    employeeId: string;
    name: string;
    onClose: () => void;
}) {
    const { t } = useTranslation('people');
    const [lastDay, setLastDay] = useState(today());
    const [reason, setReason] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const submit = () =>
        router.post(
            people.termination.store.url(employeeId),
            { lastDay, reason },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onError: setErrors,
                onSuccess: onClose,
            },
        );

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={t('terminate.title', { name })}
            description={t('terminate.description')}
            footer={
                <>
                    <Button variant="outline" onClick={onClose}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button
                        variant="destructive"
                        onClick={submit}
                        loading={processing}
                        disabled={!reason.trim()}
                    >
                        {t('terminate.submit')}
                    </Button>
                </>
            }
        >
            <div className="flex flex-col gap-4">
                <Field
                    id="terminate-last-day"
                    label={t('terminate.lastDay')}
                    error={errors.lastDay}
                    required
                >
                    <Input
                        id="terminate-last-day"
                        type="date"
                        dir="ltr"
                        value={lastDay}
                        onChange={(event) => setLastDay(event.target.value)}
                    />
                </Field>
                <Field
                    id="terminate-reason"
                    label={t('terminate.reason')}
                    error={errors.reason}
                    required
                >
                    <Textarea
                        id="terminate-reason"
                        value={reason}
                        onChange={(event) => setReason(event.target.value)}
                        maxLength={300}
                    />
                </Field>
            </div>
        </ResponsiveDialog>
    );
}
