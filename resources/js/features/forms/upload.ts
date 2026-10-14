import { type StoredFile } from './schema';

function xsrfToken(): string | null {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match?.[1] ? decodeURIComponent(match[1]) : null;
}

export class UploadError extends Error {}

/**
 * Uploads one file for a form field (private, 24 h until submitted). XHR rather than fetch so
 * the field can show progress.
 */
export function uploadFile(
    url: string,
    field: string,
    file: File,
    onProgress: (percent: number) => void,
): Promise<StoredFile> {
    return new Promise((resolve, reject) => {
        const request = new XMLHttpRequest();
        const body = new FormData();
        body.append('field', field);
        body.append('file', file);

        request.open('POST', url);
        request.setRequestHeader('Accept', 'application/json');
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        request.setRequestHeader('Accept-Language', document.documentElement.lang || 'en');
        const token = xsrfToken();
        if (token) {
            request.setRequestHeader('X-XSRF-TOKEN', token);
        }

        request.upload.onprogress = (event) => {
            if (event.lengthComputable) {
                onProgress(Math.round((event.loaded / event.total) * 100));
            }
        };
        request.onload = () => {
            const payload = (() => {
                try {
                    return JSON.parse(request.responseText) as Record<string, unknown>;
                } catch {
                    return null;
                }
            })();

            if (request.status >= 200 && request.status < 300 && payload) {
                resolve(payload as StoredFile);
                return;
            }

            const error = payload?.error as
                { message?: string; details?: Record<string, string[]> } | undefined;
            const errors = payload?.errors as Record<string, string[]> | undefined;
            reject(
                new UploadError(
                    error?.details?.file?.[0] ??
                        errors?.file?.[0] ??
                        error?.message ??
                        (payload?.message as string | undefined) ??
                        request.statusText,
                ),
            );
        };
        request.onerror = () => reject(new UploadError(request.statusText || 'Network error'));
        request.send(body);
    });
}

/** Digits after the decimal point for a currency (SAR 2, KWD 3, JPY 0). */
export function minorDigits(currency: string): number {
    try {
        return (
            new Intl.NumberFormat('en', { style: 'currency', currency }).resolvedOptions()
                .maximumFractionDigits ?? 2
        );
    } catch {
        return 2;
    }
}
