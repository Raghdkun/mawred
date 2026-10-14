import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';

/** A labelled on/off setting row (DSActions toggle). */
export function SwitchField({
    id,
    label,
    hint,
    checked,
    onCheckedChange,
    disabled,
}: {
    id: string;
    label: string;
    hint?: string;
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
    disabled?: boolean;
}) {
    return (
        <div className="flex items-center justify-between gap-4 rounded-md border border-border p-3">
            <div className="flex flex-col">
                <Label htmlFor={id} className="text-sm font-medium">
                    {label}
                </Label>
                {hint ? (
                    <span id={`${id}-hint`} className="text-caption text-muted-foreground">
                        {hint}
                    </span>
                ) : null}
            </div>
            <Switch
                id={id}
                checked={checked}
                onCheckedChange={onCheckedChange}
                disabled={disabled}
                aria-describedby={hint ? `${id}-hint` : undefined}
            />
        </div>
    );
}
