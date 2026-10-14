import {
    DndContext,
    type DragEndEvent,
    DragOverlay,
    type DragStartEvent,
    KeyboardSensor,
    PointerSensor,
    closestCenter,
    useDraggable,
    useDroppable,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import {
    SortableContext,
    sortableKeyboardCoordinates,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { AlertCircle, Eye, GripVertical, Plus } from 'lucide-react';
import { type ReactNode, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useLocale } from '@/hooks/use-locale';
import { cn } from '@/lib/utils';
import { type FieldType, type FormField, isPicker, textOf } from '../schema';
import { FIELD_ICONS, PALETTE } from './field-icons';
import { type Builder } from './use-builder';

const PALETTE_PREFIX = 'palette:';
const SECTION_PREFIX = 'section:';

/** Palette + canvas share one drag context, so palette items drop straight into sections. */
export function BuilderDnd({ builder, children }: { builder: Builder; children: ReactNode }) {
    const { t } = useTranslation('forms');
    const [dragging, setDragging] = useState<string | null>(null);
    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
        useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
    );

    const sectionOf = (fieldKey: string) =>
        builder.schema.pages
            .flatMap((p) => p.sections)
            .find((s) => s.fields.some((f) => f.key === fieldKey));

    const onDragEnd = (event: DragEndEvent) => {
        setDragging(null);
        const active = String(event.active.id);
        const over = event.over ? String(event.over.id) : null;

        if (!over) return;

        let targetSection: string | undefined;
        let targetIndex: number | undefined;

        if (over.startsWith(SECTION_PREFIX)) {
            targetSection = over.slice(SECTION_PREFIX.length);
            targetIndex = builder.schema.pages
                .flatMap((p) => p.sections)
                .find((s) => s.key === targetSection)?.fields.length;
        } else {
            const section = sectionOf(over);
            targetSection = section?.key;
            targetIndex = section?.fields.findIndex((f) => f.key === over);
        }

        if (!targetSection || targetIndex === undefined || targetIndex < 0) return;

        if (active.startsWith(PALETTE_PREFIX)) {
            builder.addField(
                active.slice(PALETTE_PREFIX.length) as FieldType,
                targetSection,
                targetIndex,
            );
        } else if (active !== over) {
            builder.moveField(active, targetSection, targetIndex);
        }
    };

    const onDragStart = (event: DragStartEvent) => setDragging(String(event.active.id));

    return (
        <DndContext
            sensors={sensors}
            collisionDetection={closestCenter}
            onDragStart={onDragStart}
            onDragEnd={onDragEnd}
            onDragCancel={() => setDragging(null)}
            accessibility={{
                screenReaderInstructions: { draggable: t('builder.dnd.instructions') },
                announcements: {
                    onDragStart: () => t('builder.dnd.picked'),
                    onDragOver: () => undefined,
                    onDragEnd: ({ over }) =>
                        over ? t('builder.dnd.dropped') : t('builder.dnd.cancelled'),
                    onDragCancel: () => t('builder.dnd.cancelled'),
                },
            }}
        >
            {children}
            <DragOverlay>
                {dragging?.startsWith(PALETTE_PREFIX) ? (
                    <div className="rounded-md border border-ring bg-card px-3 py-2 text-small shadow-lg">
                        {t(`types.${dragging.slice(PALETTE_PREFIX.length)}`)}
                    </div>
                ) : null}
            </DragOverlay>
        </DndContext>
    );
}

/** Field palette (reading-start side): drag into the canvas, or press to add to the current section. */
export function Palette({ builder, isPublic }: { builder: Builder; isPublic: boolean }) {
    const { t } = useTranslation('forms');

    return (
        <nav aria-label={t('builder.palette')} className="flex flex-col gap-4">
            {PALETTE.map(({ group, types }) => (
                <section key={group} className="flex flex-col gap-1.5">
                    <h3 className="text-caption font-semibold tracking-wide text-muted-foreground uppercase">
                        {t(`builder.paletteGroups.${group}`)}
                    </h3>
                    <ul className="grid grid-cols-1 gap-1">
                        {types
                            .filter((type) => !(isPublic && isPicker(type)))
                            .map((type) => (
                                <PaletteItem
                                    key={type}
                                    type={type}
                                    onAdd={() =>
                                        builder.addField(
                                            type,
                                            builder.selection?.kind === 'section'
                                                ? builder.selection.key
                                                : undefined,
                                        )
                                    }
                                />
                            ))}
                    </ul>
                </section>
            ))}
        </nav>
    );
}

function PaletteItem({ type, onAdd }: { type: FieldType; onAdd: () => void }) {
    const { t } = useTranslation('forms');
    const { attributes, listeners, setNodeRef } = useDraggable({ id: `${PALETTE_PREFIX}${type}` });
    const Icon = FIELD_ICONS[type];

    return (
        <li>
            <button
                ref={setNodeRef}
                type="button"
                {...listeners}
                {...attributes}
                onClick={onAdd}
                aria-label={t('builder.addType', { type: t(`types.${type}`) })}
                className="flex h-10 w-full items-center gap-2.5 rounded-md border border-border bg-card px-3 text-start text-small transition-colors duration-150 ease-soft hover:border-ring hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
                <Icon aria-hidden className="size-4 shrink-0 text-muted-foreground" />
                {t(`types.${type}`)}
            </button>
        </li>
    );
}

/** The canvas: the active page's sections, with fields reorderable by drag or keyboard. */
export function Canvas({
    builder,
    problems,
}: {
    builder: Builder;
    problems: Record<string, string[]>;
}) {
    const { t } = useTranslation('forms');
    const { locale } = useLocale();
    const page =
        builder.schema.pages.find((p) => p.key === builder.activePage) ?? builder.schema.pages[0];

    if (!page) return null;

    return (
        <div className="flex flex-col gap-4">
            {page.sections.map((section) => (
                <SectionDrop key={section.key} id={section.key}>
                    <button
                        type="button"
                        onClick={() => builder.setSelection({ kind: 'section', key: section.key })}
                        aria-pressed={
                            builder.selection?.kind === 'section' &&
                            builder.selection.key === section.key
                        }
                        className={cn(
                            'mb-2 flex w-full items-baseline justify-between gap-2 rounded-md px-1 py-1 text-start focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                            builder.selection?.kind === 'section' &&
                                builder.selection.key === section.key &&
                                'bg-accent text-accent-foreground',
                        )}
                    >
                        <span className="text-body font-semibold">
                            {textOf(section.title, locale) || t('builder.untitledSection')}
                        </span>
                        <span className="text-caption text-muted-foreground">
                            {t('builder.sectionSettings')}
                        </span>
                    </button>
                    <SortableContext
                        items={section.fields.map((f) => f.key)}
                        strategy={verticalListSortingStrategy}
                    >
                        <ul className="flex flex-col gap-2">
                            {section.fields.map((field) => (
                                <FieldCard
                                    key={field.key}
                                    field={field}
                                    selected={
                                        builder.selection?.kind === 'field' &&
                                        builder.selection.key === field.key
                                    }
                                    problems={problems[field.key] ?? []}
                                    onSelect={() =>
                                        builder.setSelection({ kind: 'field', key: field.key })
                                    }
                                />
                            ))}
                        </ul>
                    </SortableContext>
                    {section.fields.length === 0 ? (
                        <p className="rounded-md border border-dashed border-border p-6 text-center text-small text-muted-foreground">
                            {t('builder.emptySection')}
                        </p>
                    ) : null}
                </SectionDrop>
            ))}
            <Button
                type="button"
                variant="outline"
                className="self-start"
                onClick={builder.addSection}
            >
                <Plus aria-hidden />
                {t('builder.addSection')}
            </Button>
        </div>
    );
}

function SectionDrop({ id, children }: { id: string; children: ReactNode }) {
    const { setNodeRef, isOver } = useDroppable({ id: `${SECTION_PREFIX}${id}` });

    return (
        <section
            ref={setNodeRef}
            className={cn(
                'rounded-lg bg-card p-4 shadow-sm inset-shadow-highlight',
                isOver && 'ring-2 ring-ring/50',
            )}
        >
            {children}
        </section>
    );
}

function FieldCard({
    field,
    selected,
    problems,
    onSelect,
}: {
    field: FormField;
    selected: boolean;
    problems: string[];
    onSelect: () => void;
}) {
    const { t } = useTranslation('forms');
    const { locale } = useLocale();
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
        id: field.key,
    });
    const Icon = FIELD_ICONS[field.type];
    const label = textOf(field.label, locale) || t('builder.untitledField');

    return (
        <li
            ref={setNodeRef}
            style={{ transform: CSS.Transform.toString(transform), transition }}
            className={cn(
                'flex items-stretch rounded-md border bg-card',
                selected ? 'border-ring ring-2 ring-ring/30' : 'border-border',
                isDragging && 'opacity-60',
            )}
        >
            <button
                type="button"
                {...attributes}
                {...listeners}
                aria-label={t('builder.dragField', { label })}
                className="flex w-9 shrink-0 cursor-grab items-center justify-center rounded-s-md text-muted-foreground hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none active:cursor-grabbing"
            >
                <GripVertical aria-hidden className="size-4" />
            </button>
            <button
                type="button"
                onClick={onSelect}
                aria-pressed={selected}
                className="flex min-h-12 min-w-0 flex-1 items-center gap-3 px-3 py-2 text-start focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
                <Icon aria-hidden className="size-4 shrink-0 text-muted-foreground" />
                <span className="flex min-w-0 flex-1 flex-col">
                    <span className="truncate text-sm font-medium">{label}</span>
                    <span className="text-caption text-muted-foreground">
                        {t(`types.${field.type}`)}
                    </span>
                </span>
                {field.required ? (
                    <Badge variant="outline">
                        {typeof field.required === 'object'
                            ? t('builder.badges.conditionallyRequired')
                            : t('builder.badges.required')}
                    </Badge>
                ) : null}
                {field.visible ? (
                    <Badge variant="outline">
                        <Eye aria-hidden />
                        {t('builder.badges.conditional')}
                    </Badge>
                ) : null}
                {problems.length > 0 ? (
                    <span className="flex items-center gap-1 text-caption text-danger">
                        <AlertCircle aria-hidden className="size-4" />
                        <span className="sr-only">{t('builder.hasProblems')}</span>
                    </span>
                ) : null}
            </button>
        </li>
    );
}
