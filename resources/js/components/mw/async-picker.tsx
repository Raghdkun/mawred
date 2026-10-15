import { useInfiniteQuery } from '@tanstack/react-query';
import { Check, ChevronsUpDown, X } from 'lucide-react';
import { useDeferredValue, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { api, type CursorPage } from '@/lib/api';
import { cn } from '@/lib/utils';

export type PickerOption = { id: string; label: string; description?: string };

/**
 * Async searchable picker (DS "Async searchable picker"; brief §3): search-first, server-paged,
 * "load more" at the end. Works at hundreds of branches and thousands of people.
 */
export function AsyncPicker<T>({
    endpoint,
    queryKey,
    toOption,
    value,
    onChange,
    placeholder,
    label,
    className,
}: {
    endpoint: string;
    queryKey: string;
    toOption: (item: T) => PickerOption;
    value: PickerOption | null;
    onChange: (option: PickerOption | null) => void;
    placeholder: string;
    label: string;
    className?: string;
}) {
    const { t } = useTranslation('common');
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const deferred = useDeferredValue(search);

    const query = useInfiniteQuery({
        queryKey: [queryKey, deferred],
        queryFn: ({ pageParam }) =>
            api<CursorPage<T>>(endpoint, {
                params: { search: deferred, cursor: pageParam, per_page: 20 },
            }),
        initialPageParam: null as string | null,
        getNextPageParam: (last) => last.meta.next_cursor,
        enabled: open,
    });

    const options = query.data?.pages.flatMap((page) => page.data.map(toOption)) ?? [];

    return (
        <div className={cn('flex items-center gap-1', className)}>
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <Button
                        variant="outline"
                        role="combobox"
                        aria-expanded={open}
                        aria-label={label}
                        className="w-full min-w-0 shrink justify-between font-normal"
                    >
                        <span className={cn('truncate', !value && 'text-muted-foreground')}>
                            {value?.label ?? placeholder}
                        </span>
                        <ChevronsUpDown aria-hidden className="size-4 text-muted-foreground" />
                    </Button>
                </PopoverTrigger>
                <PopoverContent
                    className="w-[--radix-popover-trigger-width] min-w-64 p-0"
                    align="start"
                >
                    <Command shouldFilter={false}>
                        <CommandInput
                            value={search}
                            onValueChange={setSearch}
                            placeholder={placeholder}
                        />
                        <CommandList>
                            {query.isFetching && options.length === 0 ? (
                                <div role="status" className="p-3 text-small text-muted-foreground">
                                    {t('states.loading')}
                                </div>
                            ) : (
                                <CommandEmpty>{t('states.no-results.title')}</CommandEmpty>
                            )}
                            <CommandGroup>
                                {options.map((option) => (
                                    <CommandItem
                                        key={option.id}
                                        value={option.id}
                                        onSelect={() => {
                                            onChange(option);
                                            setOpen(false);
                                        }}
                                    >
                                        <Check
                                            aria-hidden
                                            className={cn(
                                                'size-4',
                                                value?.id === option.id
                                                    ? 'opacity-100'
                                                    : 'opacity-0',
                                            )}
                                        />
                                        <span className="flex min-w-0 flex-col">
                                            <span className="truncate">{option.label}</span>
                                            {option.description ? (
                                                <bdi className="truncate text-caption text-muted-foreground">
                                                    {option.description}
                                                </bdi>
                                            ) : null}
                                        </span>
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                            {query.hasNextPage ? (
                                <button
                                    type="button"
                                    onClick={() => void query.fetchNextPage()}
                                    className="w-full border-t border-border p-2 text-small text-link hover:bg-muted"
                                    disabled={query.isFetchingNextPage}
                                >
                                    {query.isFetchingNextPage
                                        ? t('states.loading')
                                        : t('actions.loadMore')}
                                </button>
                            ) : null}
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>
            {value ? (
                <Button
                    variant="ghost"
                    size="icon-sm"
                    onClick={() => onChange(null)}
                    aria-label={t('actions.clear')}
                >
                    <X aria-hidden />
                </Button>
            ) : null}
        </div>
    );
}
