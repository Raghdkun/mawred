import { router, usePage } from '@inertiajs/react';
import { VenetianMask } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import impersonate from '@/routes/access/impersonate';

/** Persistent banner while a Super Admin is viewing Mawred as someone else (brief §6). */
export function ImpersonationBanner() {
    const { t } = useTranslation('shell');
    const { impersonation } = usePage().props;

    if (!impersonation) {
        return null;
    }

    return (
        <div
            role="status"
            className="sticky top-0 z-50 flex flex-wrap items-center justify-center gap-3 border-b border-warning-border bg-warning-bg px-4 py-2 text-small text-warning"
        >
            <VenetianMask aria-hidden className="size-4" />
            <span>
                {t('impersonation.notice', {
                    target: impersonation.target,
                    impersonator: impersonation.impersonator,
                })}
            </span>
            <Button
                size="sm"
                variant="outline"
                onClick={() => router.delete(impersonate.stop.url())}
            >
                {t('impersonation.stop')}
            </Button>
        </div>
    );
}
