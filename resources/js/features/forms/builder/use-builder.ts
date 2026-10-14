import { useCallback, useMemo, useState } from 'react';
import {
    type FieldType,
    type FormField,
    type FormPage,
    type FormSchema,
    type FormSection,
    allFields,
    hasOptions,
} from '../schema';

export type Selection =
    | { kind: 'field'; key: string }
    | { kind: 'section'; key: string }
    | { kind: 'page'; key: string }
    | null;

/** A key from a label: lowercase ASCII, underscores, unique in the form (FormSchema::KEY_PATTERN). */
export function makeKey(label: string, taken: string[], fallback = 'field'): string {
    const base =
        label
            .toLowerCase()
            .normalize('NFKD')
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '')
            .replace(/^[^a-z]+/, '')
            .slice(0, 32) || fallback;
    let key = base;

    for (let i = 2; taken.includes(key); i++) {
        key = `${base}_${i}`;
    }

    return key;
}

export function newField(type: FieldType, taken: string[]): FormField {
    const field: FormField = { key: makeKey(type, taken), type, label: { en: '', ar: '' } };

    if (hasOptions(type)) {
        field.options = [
            { value: 'option_1', label: { en: 'Option 1', ar: '' } },
            { value: 'option_2', label: { en: 'Option 2', ar: '' } },
        ];
    }

    if (type === 'calculated') {
        field.calc = 0;
    }

    if (type === 'file') {
        field.validation = { maxFiles: 1, maxSizeKb: 10240 };
    }

    return field;
}

const mapSections = (
    schema: FormSchema,
    fn: (section: FormSection, page: FormPage) => FormSection,
): FormSchema => ({
    ...schema,
    pages: schema.pages.map((page) => ({
        ...page,
        sections: page.sections.map((section) => fn(section, page)),
    })),
});

/**
 * Builder state (handoff 14): the working schema, the selection and whether anything changed
 * since the last save. Every edit is a pure transform of the schema.
 */
export function useBuilder(initial: FormSchema) {
    const [schema, setSchema] = useState<FormSchema>(initial);
    const [saved, setSaved] = useState(() => JSON.stringify(initial));
    const [selection, setSelection] = useState<Selection>(null);
    const [activePage, setActivePage] = useState(initial.pages[0]?.key ?? '');

    const dirty = useMemo(() => JSON.stringify(schema) !== saved, [schema, saved]);
    const keys = useMemo(() => allFields(schema).map((field) => field.key), [schema]);

    const markSaved = useCallback(() => setSaved(JSON.stringify(schema)), [schema]);
    const reset = useCallback((next: FormSchema) => {
        setSchema(next);
        setSaved(JSON.stringify(next));
        setSelection(null);
        setActivePage(next.pages[0]?.key ?? '');
    }, []);

    const addField = useCallback(
        (type: FieldType, sectionKey?: string, index?: number) => {
            const field = newField(type, keys);
            const target =
                sectionKey ?? schema.pages.find((p) => p.key === activePage)?.sections.at(-1)?.key;

            setSchema((current) =>
                mapSections(current, (section) => {
                    if (section.key !== target) return section;
                    const fields = [...section.fields];
                    fields.splice(index ?? fields.length, 0, field);
                    return { ...section, fields };
                }),
            );
            setSelection({ kind: 'field', key: field.key });
        },
        [keys, schema.pages, activePage],
    );

    const updateField = useCallback((key: string, next: FormField) => {
        setSchema((current) =>
            mapSections(current, (section) => ({
                ...section,
                fields: section.fields.map((f) => (f.key === key ? next : f)),
            })),
        );
        setSelection((current) =>
            current?.kind === 'field' && current.key === key
                ? { kind: 'field', key: next.key }
                : current,
        );
    }, []);

    const removeField = useCallback((key: string) => {
        setSchema((current) =>
            mapSections(current, (section) => ({
                ...section,
                fields: section.fields.filter((f) => f.key !== key),
            })),
        );
        setSelection(null);
    }, []);

    const duplicateField = useCallback(
        (key: string) => {
            setSchema((current) =>
                mapSections(current, (section) => {
                    const index = section.fields.findIndex((f) => f.key === key);
                    if (index < 0) return section;
                    const source = section.fields[index];
                    const copy = { ...structuredClone(source), key: makeKey(source.key, keys) };
                    const fields = [...section.fields];
                    fields.splice(index + 1, 0, copy);
                    return { ...section, fields };
                }),
            );
        },
        [keys],
    );

    /** Moves a field to a position in a section (drag and drop, or the keyboard menu). */
    const moveField = useCallback((key: string, toSection: string, toIndex: number) => {
        setSchema((current) => {
            const field = allFields(current).find((f) => f.key === key);
            if (!field) return current;
            const without = mapSections(current, (section) => ({
                ...section,
                fields: section.fields.filter((f) => f.key !== key),
            }));

            return mapSections(without, (section) => {
                if (section.key !== toSection) return section;
                const fields = [...section.fields];
                fields.splice(Math.max(0, Math.min(toIndex, fields.length)), 0, field);
                return { ...section, fields };
            });
        });
    }, []);

    const updateSection = useCallback((key: string, next: FormSection) => {
        setSchema((current) =>
            mapSections(current, (section) => (section.key === key ? next : section)),
        );
    }, []);

    const addSection = useCallback(() => {
        const taken = schema.pages.flatMap((p) => p.sections.map((s) => s.key));
        const key = makeKey('section', taken, 'section');

        setSchema((current) => ({
            ...current,
            pages: current.pages.map((page) =>
                page.key === activePage
                    ? {
                          ...page,
                          sections: [
                              ...page.sections,
                              { key, title: { en: '', ar: '' }, fields: [] },
                          ],
                      }
                    : page,
            ),
        }));
        setSelection({ kind: 'section', key });
    }, [schema.pages, activePage]);

    const removeSection = useCallback((key: string) => {
        setSchema((current) => ({
            ...current,
            pages: current.pages.map((page) =>
                page.sections.length > 1
                    ? { ...page, sections: page.sections.filter((s) => s.key !== key) }
                    : page,
            ),
        }));
        setSelection(null);
    }, []);

    const addPage = useCallback(() => {
        const pageKey = makeKey(
            'page',
            schema.pages.map((p) => p.key),
            'page',
        );
        const sectionKey = makeKey(
            'section',
            schema.pages.flatMap((p) => p.sections.map((s) => s.key)),
            'section',
        );

        setSchema((current) => ({
            ...current,
            pages: [
                ...current.pages,
                {
                    key: pageKey,
                    title: { en: '', ar: '' },
                    sections: [{ key: sectionKey, title: null, fields: [] }],
                },
            ],
        }));
        setActivePage(pageKey);
        setSelection({ kind: 'page', key: pageKey });
    }, [schema.pages]);

    const updatePage = useCallback((key: string, next: FormPage) => {
        setSchema((current) => ({
            ...current,
            pages: current.pages.map((page) => (page.key === key ? next : page)),
        }));
    }, []);

    const removePage = useCallback(
        (key: string) => {
            if (schema.pages.length < 2) return;
            setSchema((current) => ({
                ...current,
                pages: current.pages.filter((page) => page.key !== key),
            }));
            setActivePage(schema.pages.find((page) => page.key !== key)?.key ?? '');
            setSelection(null);
        },
        [schema.pages],
    );

    const selectedField =
        selection?.kind === 'field'
            ? (allFields(schema).find((f) => f.key === selection.key) ?? null)
            : null;

    return {
        schema,
        dirty,
        keys,
        selection,
        setSelection,
        selectedField,
        activePage,
        setActivePage,
        markSaved,
        reset,
        addField,
        updateField,
        removeField,
        duplicateField,
        moveField,
        addSection,
        updateSection,
        removeSection,
        addPage,
        updatePage,
        removePage,
    };
}

export type Builder = ReturnType<typeof useBuilder>;
