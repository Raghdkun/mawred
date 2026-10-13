import { useTranslation } from 'react-i18next';
import { Toaster } from '@/components/ui/sonner';

/** Toasts follow the active language: reading-end corner, matching text direction. */
export function LocalizedToaster() {
    const { i18n } = useTranslation();
    const dir = i18n.dir(i18n.language);

    return <Toaster dir={dir} position={dir === 'rtl' ? 'bottom-left' : 'bottom-right'} />;
}
