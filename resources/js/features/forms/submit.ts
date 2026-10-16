import { type FormField, type StoredFile } from './schema';

/**
 * Answers as the server takes them: a file field holds the uploaded files, but only their upload
 * tokens are sent (the server checks each belongs to this person and field, ADR-0008).
 */
export function forServer(
    answers: Record<string, unknown>,
    fields: Pick<FormField, 'key' | 'type'>[],
): Record<string, unknown> {
    const out = { ...answers };

    for (const field of fields) {
        const value = out[field.key];

        if (field.type === 'file' && Array.isArray(value)) {
            out[field.key] = value.map((file) =>
                typeof file === 'string' ? file : (file as StoredFile).id,
            );
        }
    }

    return out;
}
