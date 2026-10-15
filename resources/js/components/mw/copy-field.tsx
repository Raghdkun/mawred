import { Check, Copy } from 'lucide-react';
import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useClipboard } from '@/hooks/use-clipboard';

/** Read-only value with a copy button (setup wizard "copy field"). URLs and tokens stay LTR. */
export function CopyField({ label, value }: { label: string; value: string }) {
    const { t } = useTranslation('common');
    const id = useId();
    const [copied, copy] = useClipboard();

    return (
        <div className="flex flex-col gap-1.5">
            <Label htmlFor={id}>{label}</Label>
            <div className="flex gap-2">
                <Input
                    id={id}
                    dir="ltr"
                    readOnly
                    value={value}
                    className="font-mono text-caption"
                    onFocus={(event) => event.currentTarget.select()}
                />
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    aria-label={`${t('actions.copy')}: ${label}`}
                    onClick={() => void copy(value)}
                >
                    {copied === value ? <Check aria-hidden /> : <Copy aria-hidden />}
                </Button>
            </div>
            <span aria-live="polite" className="sr-only">
                {copied === value ? t('actions.copied') : ''}
            </span>
        </div>
    );
}
