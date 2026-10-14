import { useQuery } from '@tanstack/react-query';
import { useDeferredValue } from 'react';
import { api } from '@/lib/api';

export type SearchGroup = { type: string; items: App.Support.Search.SearchHitData[] };

/** Server-side global search (ADR-0013), scoped per type; starts at two characters. */
export function useGlobalSearch(term: string, enabled: boolean) {
    const deferred = useDeferredValue(term.trim());

    return useQuery({
        queryKey: ['global-search', deferred],
        queryFn: ({ signal }) =>
            api<{ data: SearchGroup[] }>('search', { params: { q: deferred }, signal }).then(
                (response) => response.data,
            ),
        enabled: enabled && deferred.length >= 2,
        staleTime: 30_000,
        placeholderData: (previous) => previous,
    });
}
