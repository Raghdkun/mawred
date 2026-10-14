/**
 * First-party calls to /api/v1 with Sanctum's stateful (cookie) auth (ADR-0012, ADR-0020).
 * Errors follow the API envelope: { error: { code, message, details, trace_id } }.
 */
export class ApiError extends Error {
    constructor(
        public readonly status: number,
        public readonly code: string,
        message: string,
        public readonly details: Record<string, string[]> = {},
        public readonly traceId: string | null = null,
    ) {
        super(message);
    }
}

export function xsrfToken(): string | null {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match?.[1] ? decodeURIComponent(match[1]) : null;
}

export async function api<T>(
    path: string,
    init: RequestInit & { params?: Record<string, string | number | null | undefined> } = {},
): Promise<T> {
    const url = new URL(`/api/v1/${path.replace(/^\//, '')}`, window.location.origin);

    for (const [key, value] of Object.entries(init.params ?? {})) {
        if (value !== null && value !== undefined && value !== '') {
            url.searchParams.set(key, String(value));
        }
    }

    const headers = new Headers(init.headers);
    headers.set('Accept', 'application/json');
    headers.set('X-Requested-With', 'XMLHttpRequest');
    headers.set('Accept-Language', document.documentElement.lang || 'en');

    const token = xsrfToken();
    if (token) {
        headers.set('X-XSRF-TOKEN', token);
    }

    const response = await fetch(url, { ...init, headers, credentials: 'same-origin' });

    if (!response.ok) {
        const body = (await response.json().catch(() => null)) as {
            error?: {
                code?: string;
                message?: string;
                details?: Record<string, string[]>;
                trace_id?: string;
            };
        } | null;

        throw new ApiError(
            response.status,
            body?.error?.code ?? 'http_error',
            body?.error?.message ?? response.statusText,
            body?.error?.details ?? {},
            body?.error?.trace_id ?? null,
        );
    }

    return (await response.json()) as T;
}

export type CursorPage<T> = {
    data: T[];
    meta: { next_cursor: string | null; prev_cursor: string | null; per_page: number };
};

/**
 * JSON POST to one of the app's own web routes (e.g. a change preview), with the same CSRF
 * cookie. Laravel 422s (`{ message, errors }`) and the API envelope both become an ApiError.
 */
export async function postJson<T>(url: string, body: unknown): Promise<T> {
    const headers = new Headers({
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'Accept-Language': document.documentElement.lang || 'en',
    });

    const token = xsrfToken();
    if (token) {
        headers.set('X-XSRF-TOKEN', token);
    }

    const response = await fetch(url, {
        method: 'POST',
        headers,
        body: JSON.stringify(body),
        credentials: 'same-origin',
    });

    if (!response.ok) {
        const payload = (await response.json().catch(() => null)) as {
            message?: string;
            errors?: Record<string, string[]>;
            error?: {
                code?: string;
                message?: string;
                details?: Record<string, string[]>;
                trace_id?: string;
            };
        } | null;

        throw new ApiError(
            response.status,
            payload?.error?.code ?? (response.status === 422 ? 'validation_failed' : 'http_error'),
            payload?.error?.message ?? payload?.message ?? response.statusText,
            payload?.error?.details ?? payload?.errors ?? {},
            payload?.error?.trace_id ?? null,
        );
    }

    return (await response.json()) as T;
}
