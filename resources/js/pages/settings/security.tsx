import { Form, Head, router, usePage } from '@inertiajs/react';
import { Monitor, ShieldAlert } from 'lucide-react';
import { useRef } from 'react';
import { useTranslation } from 'react-i18next';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import { Section } from '@/components/mw/page-header';
import InputError from '@/components/input-error';
import ManagePasskeys, { type Props as ManagePasskeysProps } from '@/components/manage-passkeys';
import ManageTwoFactor, {
    type Props as ManageTwoFactorProps,
} from '@/components/manage-two-factor';
import PasswordInput from '@/components/password-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useLocale } from '@/hooks/use-locale';
import { formatDate } from '@/lib/format';
import sessions from '@/routes/sessions';

type Session = App.Domain.Access.Data.UserSessionData;
type HistoryEntry = {
    event: string | null;
    at: string | null;
    ip: string | null;
    channel: string | null;
};

type Props = {
    passwordRules: string;
    sessions: Session[];
    loginHistory: HistoryEntry[];
} & ManagePasskeysProps &
    ManageTwoFactorProps;

export default function Security(props: Props) {
    const { t } = useTranslation('settings');
    const { locale } = useLocale();
    const { auth } = usePage().props;
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);
    const mustEnrol = (auth?.access.requiresTwoFactor ?? false) && !props.twoFactorEnabled;

    return (
        <>
            <Head title={t('security.pageTitle')} />

            {mustEnrol ? (
                <div
                    role="alert"
                    className="flex items-start gap-3 rounded-lg border border-warning-border bg-warning-bg p-4 text-small text-warning"
                >
                    <ShieldAlert aria-hidden className="mt-0.5 size-5 shrink-0" />
                    <p>{t('security.twoFactor.requiredByRole')}</p>
                </div>
            ) : null}

            <div className="rounded-lg bg-card p-5 shadow-sm inset-shadow-highlight sm:p-6">
                <ManageTwoFactor
                    canManageTwoFactor={props.canManageTwoFactor}
                    requiresConfirmation={props.requiresConfirmation}
                    twoFactorEnabled={props.twoFactorEnabled}
                />
            </div>

            <Section
                title={t('security.password.title')}
                description={t('security.password.description')}
            >
                <Form
                    {...SecurityController.update.form()}
                    options={{ preserveScroll: true }}
                    resetOnError={['password', 'password_confirmation', 'current_password']}
                    resetOnSuccess
                    onError={(errors) => {
                        if (errors.password) {
                            passwordInput.current?.focus();
                        }

                        if (errors.current_password) {
                            currentPasswordInput.current?.focus();
                        }
                    }}
                    className="flex flex-col gap-5"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="current_password">
                                    {t('security.password.current')}
                                </Label>
                                <PasswordInput
                                    id="current_password"
                                    ref={currentPasswordInput}
                                    name="current_password"
                                    autoComplete="current-password"
                                />
                                <InputError message={errors.current_password} />
                            </div>
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="password">{t('security.password.new')}</Label>
                                <PasswordInput
                                    id="password"
                                    ref={passwordInput}
                                    name="password"
                                    autoComplete="new-password"
                                    passwordrules={props.passwordRules}
                                />
                                <InputError message={errors.password} />
                            </div>
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="password_confirmation">
                                    {t('security.password.confirm')}
                                </Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    autoComplete="new-password"
                                    passwordrules={props.passwordRules}
                                />
                                <InputError message={errors.password_confirmation} />
                            </div>
                            <Button
                                className="self-start"
                                loading={processing}
                                data-test="update-password-button"
                            >
                                {t('security.password.save')}
                            </Button>
                        </>
                    )}
                </Form>
            </Section>

            <div className="rounded-lg bg-card p-5 shadow-sm inset-shadow-highlight sm:p-6">
                <ManagePasskeys
                    canManagePasskeys={props.canManagePasskeys}
                    passkeys={props.passkeys}
                />
            </div>

            <Section
                title={t('security.sessions.title')}
                description={t('security.sessions.description')}
            >
                <ul className="flex flex-col divide-y divide-border">
                    {props.sessions.map((session) => (
                        <li key={session.id} className="flex flex-wrap items-center gap-3 py-3">
                            <Monitor aria-hidden className="size-5 text-muted-foreground" />
                            <div className="flex min-w-0 flex-1 flex-col">
                                <span className="flex items-center gap-2 text-sm font-medium">
                                    {session.device}
                                    {session.current ? (
                                        <Badge variant="secondary">
                                            {t('security.sessions.current')}
                                        </Badge>
                                    ) : null}
                                </span>
                                <span className="text-caption text-muted-foreground">
                                    <bdi dir="ltr">{session.ip}</bdi> ·{' '}
                                    {t('security.sessions.lastSeen', {
                                        when: formatDate(session.lastSeenAt, locale, {
                                            dateStyle: 'medium',
                                            timeStyle: 'short',
                                        }),
                                    })}
                                </span>
                            </div>
                            {!session.current ? (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        router.delete(sessions.destroy.url(session.id), {
                                            preserveScroll: true,
                                        })
                                    }
                                >
                                    {t('security.sessions.signOut')}
                                </Button>
                            ) : null}
                        </li>
                    ))}
                </ul>
            </Section>

            <Section
                title={t('security.history.title')}
                description={t('security.history.description')}
            >
                {props.loginHistory.length === 0 ? (
                    <p className="text-small text-muted-foreground">
                        {t('security.history.empty')}
                    </p>
                ) : (
                    <ul className="flex flex-col divide-y divide-border">
                        {props.loginHistory.map((entry, index) => (
                            <li
                                key={`${entry.at}-${index}`}
                                className="flex flex-wrap items-center justify-between gap-2 py-2.5 text-sm"
                            >
                                <span
                                    className={
                                        entry.event === 'login.failed' ||
                                        entry.event === '2fa.failed'
                                            ? 'text-danger'
                                            : 'text-foreground'
                                    }
                                >
                                    {t(`security.history.events.${entry.event ?? 'login'}`, {
                                        defaultValue: entry.event ?? '',
                                    })}
                                </span>
                                <span className="text-caption text-muted-foreground">
                                    {entry.at
                                        ? formatDate(entry.at, locale, {
                                              dateStyle: 'medium',
                                              timeStyle: 'short',
                                          })
                                        : null}
                                    {entry.ip ? (
                                        <>
                                            {' · '}
                                            <bdi dir="ltr">{entry.ip}</bdi>
                                        </>
                                    ) : null}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </Section>
        </>
    );
}
