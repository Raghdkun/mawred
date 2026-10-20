import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { useDeferredValue } from 'react';
import { ApiError, postJson } from '@/lib/api';
import reports from '@/routes/reports';
import { type Definition, previewable } from './definition';

/**
 * The builder's live preview (handoff 37): re-runs as the definition changes (deferred, so
 * typing stays smooth) and keeps the last result on screen while the next one loads.
 */
export function useReportPreview(definition: Definition) {
    const deferred = useDeferredValue(definition);
    const enabled = previewable(deferred);

    const query = useQuery({
        queryKey: ['report-preview', deferred],
        queryFn: () =>
            postJson<App.Domain.Reports.Data.ReportResultData>(reports.builder.preview.url(), {
                definition: deferred,
            }),
        enabled,
        placeholderData: keepPreviousData,
        retry: false,
        staleTime: 30_000,
    });

    const errors = query.error instanceof ApiError ? query.error.details : {};

    return {
        enabled,
        result: enabled ? (query.data ?? null) : null,
        loading: enabled && query.isFetching,
        failed: enabled && query.isError,
        errors,
    };
}
