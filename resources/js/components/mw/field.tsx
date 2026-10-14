import { type ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

/** Label + control + help/error (DSActions field). */
export function Field({
    id,
    label,
    error,
    hint,
    required,
    className,
    children,
}: {
    id: string;
    label: string;
    error?: string;
    hint?: string;
    required?: boolean;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={cn('flex flex-col gap-1.5', className)}>
            <Label htmlFor={id}>
                {label}
                {required ? (
                    <span aria-hidden className="text-danger">
                        *
                    </span>
                ) : null}
            </Label>
            {children}
            {hint && !error ? <p className="text-caption text-muted-foreground">{hint}</p> : null}
            <InputError message={error} />
        </div>
    );
}
