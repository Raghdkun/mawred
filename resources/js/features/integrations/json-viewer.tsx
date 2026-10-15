/** Payload viewer (handoff 41): JSON stays LTR and monospaced in both languages. */
export function JsonViewer({ value, label }: { value: unknown; label: string }) {
    return (
        <pre
            dir="ltr"
            aria-label={label}

            className="max-h-96 overflow-auto rounded-md border border-border bg-muted p-3 text-start font-mono text-caption text-foreground"
        >
            {JSON.stringify(value, null, 2)}
        </pre>
    );
}

/** Every leaf path in a payload (`Name.First`, `Entry.Version`) for the mapping pickers. */
export function payloadPaths(value: unknown, prefix = ''): string[] {
    if (value === null || typeof value !== 'object' || Array.isArray(value)) {
        return prefix ? [prefix] : [];
    }

    const paths = Object.entries(value as Record<string, unknown>).flatMap(([key, child]) =>
        child !== null && typeof child === 'object' && !Array.isArray(child)
            ? payloadPaths(child, prefix ? `${prefix}.${key}` : key)
            : [prefix ? `${prefix}.${key}` : key],
    );

    return prefix && paths.length === 0 ? [prefix] : paths;
}
