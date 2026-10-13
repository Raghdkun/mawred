import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { InputOTP, InputOTPGroup, InputOTPSlot } from '@/components/ui/input-otp';
import { OTP_MAX_LENGTH } from '@/hooks/use-two-factor-auth';
import { store } from '@/routes/two-factor/login';

/** 2FA (handoff screen 1a): 6-cell OTP that stays LTR, auto-advances, and fills on paste. */
export default function TwoFactorChallenge() {
    const { t } = useTranslation('auth');
    const [recovery, setRecovery] = useState(false);
    const [code, setCode] = useState('');

    setLayoutProps({
        title: recovery ? t('twoFactor.recoveryTitle') : t('twoFactor.codeTitle'),
        description: recovery ? t('twoFactor.recoveryDescription') : t('twoFactor.codeDescription'),
    });

    return (
        <>
            <Head title={t('twoFactor.pageTitle')} />

            <Form
                {...store.form()}
                className="flex flex-col gap-5"
                resetOnError
                resetOnSuccess={!recovery}
            >
                {({ errors, processing, clearErrors }) => (
                    <>
                        {recovery ? (
                            <div className="flex flex-col gap-1.5">
                                <Input
                                    name="recovery_code"
                                    dir="ltr"
                                    aria-label={t('twoFactor.recoveryPlaceholder')}
                                    placeholder={t('twoFactor.recoveryPlaceholder')}
                                    autoComplete="one-time-code"
                                    autoFocus
                                    required
                                />
                                <InputError message={errors.recovery_code} />
                            </div>
                        ) : (
                            <div className="flex flex-col items-center gap-2" dir="ltr">
                                <InputOTP
                                    name="code"
                                    maxLength={OTP_MAX_LENGTH}
                                    value={code}
                                    onChange={setCode}
                                    disabled={processing}
                                    pattern={REGEXP_ONLY_DIGITS}
                                    aria-label={t('twoFactor.codeLabel')}
                                    autoComplete="one-time-code"
                                    autoFocus
                                >
                                    <InputOTPGroup>
                                        {Array.from({ length: OTP_MAX_LENGTH }, (_, index) => (
                                            <InputOTPSlot
                                                key={index}
                                                index={index}
                                                className="size-12 text-h4"
                                            />
                                        ))}
                                    </InputOTPGroup>
                                </InputOTP>
                                <InputError message={errors.code} />
                            </div>
                        )}

                        <Button type="submit" size="lg" className="w-full" loading={processing}>
                            {t('twoFactor.submit')}
                        </Button>

                        <Button
                            type="button"
                            variant="link"
                            className="self-center"
                            onClick={() => {
                                setRecovery(!recovery);
                                setCode('');
                                clearErrors();
                            }}
                        >
                            {recovery ? t('twoFactor.useCode') : t('twoFactor.useRecovery')}
                        </Button>
                    </>
                )}
            </Form>
        </>
    );
}
