import { cn } from '@/lib/utils';

/** Section heading used by the kit's 2FA/passkey components (DS h4 + small description). */
export default function Heading({
    title,
    description,
    variant = 'default',
    className,
}: {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
    className?: string;
}) {
    return (
        <header className={cn('flex flex-col gap-1', className)}>
            <h2
                className={
                    variant === 'small' ? 'text-h4 text-foreground' : 'text-h3 text-foreground'
                }
            >
                {title}
            </h2>
            {description ? <p className="text-small text-muted-foreground">{description}</p> : null}
        </header>
    );
}
