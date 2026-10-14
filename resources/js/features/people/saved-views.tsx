import { router } from '@inertiajs/react';
import { Bookmark, Trash2 } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Field } from '@/components/mw/field';

type View = { name: string; query: string };

const KEY = 'mawred.people.views';

function readViews(): View[] {
    try {
        const parsed: unknown = JSON.parse(window.localStorage.getItem(KEY) ?? '[]');
        return Array.isArray(parsed)
            ? (parsed as View[]).filter((view) => typeof view?.name === 'string')
            : [];
    } catch {
        return [];
    }
}

function writeViews(views: View[]): void {
    try {
        window.localStorage.setItem(KEY, JSON.stringify(views));
    } catch {
        // Private mode or blocked storage: views simply aren't remembered.
    }
}

/**
 * Saved views (screen 7 filter bar): named filter + view combinations, kept per browser. They
 * hold no data, only the query string, so scope still applies when a view is opened.
 */
export function SavedViews({ path }: { path: string }) {
    const { t } = useTranslation('people');
    const [views, setViews] = useState<View[]>(() =>
        typeof window === 'undefined' ? [] : readViews(),
    );
    const [naming, setNaming] = useState(false);
    const [name, setName] = useState('');

    const save = (event: FormEvent) => {
        event.preventDefault();
        const query = window.location.search.replace(/^\?/, '').replace(/(^|&)cursor=[^&]*/g, '');
        const next = [
            ...views.filter((view) => view.name !== name.trim()),
            { name: name.trim(), query },
        ];
        setViews(next);
        writeViews(next);
        setNaming(false);
        setName('');
    };

    const remove = (target: string) => {
        const next = views.filter((view) => view.name !== target);
        setViews(next);
        writeViews(next);
    };

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button variant="outline" size="sm">
                        <Bookmark aria-hidden />
                        {t('views.title')}
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-64">
                    <DropdownMenuLabel>{t('views.saved')}</DropdownMenuLabel>
                    {views.length === 0 ? (
                        <p className="px-2 py-1.5 text-caption text-muted-foreground">
                            {t('views.none')}
                        </p>
                    ) : null}
                    {views.map((view) => (
                        <div key={view.name} className="flex items-center">
                            <DropdownMenuItem
                                className="flex-1"
                                onSelect={() =>
                                    router.visit(`${path}${view.query ? `?${view.query}` : ''}`)
                                }
                            >
                                {view.name}
                            </DropdownMenuItem>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                onClick={() => remove(view.name)}
                                aria-label={t('views.delete', { name: view.name })}
                            >
                                <Trash2 aria-hidden />
                            </Button>
                        </div>
                    ))}
                    <DropdownMenuSeparator />
                    <DropdownMenuItem onSelect={() => setNaming(true)}>
                        {t('views.saveCurrent')}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
            <ResponsiveDialog
                open={naming}
                onOpenChange={setNaming}
                title={t('views.saveCurrent')}
                footer={
                    <Button type="submit" form="save-view-form" disabled={!name.trim()}>
                        {t('common:actions.save')}
                    </Button>
                }
            >
                <form id="save-view-form" onSubmit={save}>
                    <Field id="view-name" label={t('views.name')}>
                        <Input
                            id="view-name"
                            value={name}
                            onChange={(event) => setName(event.target.value)}
                            maxLength={40}
                        />
                    </Field>
                </form>
            </ResponsiveDialog>
        </>
    );
}
