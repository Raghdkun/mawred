import { Search } from 'lucide-react';
import { type FormEvent } from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

/** Search-first list filter: submits on Enter (server-side search). */
export function SearchBox({
    value,
    onChange,
    onSubmit,
    label,
    className,
}: {
    value: string;
    onChange: (value: string) => void;
    onSubmit: () => void;
    label: string;
    className?: string;
}) {
    const submit = (event: FormEvent) => {
        event.preventDefault();
        onSubmit();
    };

    return (
        <form
            onSubmit={submit}
            role="search"
            className={cn('relative w-full sm:max-w-xs', className)}
        >
            <Search
                aria-hidden
                className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                type="search"
                value={value}
                onChange={(event) => onChange(event.target.value)}
                placeholder={label}
                aria-label={label}
                className="ps-9"
            />
        </form>
    );
}
