import { CircleAlert } from 'lucide-react';
import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

/** Inline field error (DSActions `.help.er`): danger text with an icon, announced to screen readers. */
export default function InputError({
    message,
    className = '',
    ...props
}: HTMLAttributes<HTMLParagraphElement> & { message?: string }) {
    return message ? (
        <p
            role="alert"
            {...props}
            className={cn('flex items-center gap-1.5 text-caption text-danger', className)}
        >
            <CircleAlert aria-hidden className="size-3.5 shrink-0" />
            {message}
        </p>
    ) : null;
}
