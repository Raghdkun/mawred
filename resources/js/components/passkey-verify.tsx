import type { UrlMethodPair } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { usePasskeyVerify } from '@laravel/passkeys/react';
import { KeyRound } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    routes?: {
        options: UrlMethodPair;
        submit: UrlMethodPair;
    };
    label?: string;
    loadingLabel?: string;
    separator?: string;
    /** Where the passkey option sits relative to the password form. */
    placement?: 'before' | 'after';
};

export default function PasskeyVerify({
    routes,
    label,
    loadingLabel,
    separator,
    placement = 'before',
}: Props = {}) {
    const { t } = useTranslation('auth');
    const { verify, isLoading, error, isSupported } = usePasskeyVerify({
        ...(routes && {
            routes: {
                options: routes.options.url,
                submit: routes.submit.url,
            },
        }),
        onSuccess: (response) => {
            router.visit(response.redirect ?? '/');
        },
    });

    if (!isSupported) {
        return null;
    }

    const divider = (
        <div className="relative">
            <div className="absolute inset-0 flex items-center">
                <Separator className="w-full" />
            </div>
            <div className="relative flex justify-center text-caption">
                <span className="bg-background px-2 text-muted-foreground">
                    {separator ?? t('login.or')}
                </span>
            </div>
        </div>
    );

    return (
        <>
            {placement === 'after' ? divider : null}
            <div className="grid gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="lg"
                    className="w-full"
                    onClick={verify}
                    disabled={isLoading}
                >
                    {isLoading ? <Spinner /> : <KeyRound aria-hidden className="size-4" />}
                    {isLoading
                        ? (loadingLabel ?? t('login.passkeyLoading'))
                        : (label ?? t('login.passkey'))}
                </Button>
                {error && <InputError message={error} className="justify-center" />}
            </div>
            {placement === 'before' ? divider : null}
        </>
    );
}
