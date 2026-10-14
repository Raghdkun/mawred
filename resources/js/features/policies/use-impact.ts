import { useCallback, useState } from 'react';
import { ApiError, postJson } from '@/lib/api';
import { type ImpactPreview } from './types';

/** Loads a change-impact preview (handoff 20) and keeps the dialog state. */
export function useImpact() {
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [preview, setPreview] = useState<ImpactPreview | null>(null);
    const [errors, setErrors] = useState<Record<string, string[]> | null>(null);

    const run = useCallback(async (url: string, payload: unknown) => {
        setOpen(true);
        setLoading(true);
        setPreview(null);
        setErrors(null);

        try {
            setPreview(await postJson<ImpactPreview>(url, payload));
        } catch (error) {
            setErrors(error instanceof ApiError ? error.details : {});
        } finally {
            setLoading(false);
        }
    }, []);

    return { open, setOpen, loading, preview, errors, failed: errors !== null, run };
}
