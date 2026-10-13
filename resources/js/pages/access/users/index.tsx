import { Head, Link, router, useForm } from '@inertiajs/react';
import { Eye, LogIn, Search, ShieldCheck, UserCog } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { AccessTabs } from '@/features/access/access-tabs';
import { useInitials } from '@/hooks/use-initials';
import { useLocale } from '@/hooks/use-locale';
import { formatDate } from '@/lib/format';
import accessImpersonate from '@/routes/access/impersonate';
import accessUsers from '@/routes/access/users';

type User = App.Domain.Access.Data.UserAccountData;
type Role = App.Domain.Access.Data.RoleSummaryData;

type Props = {
    users: { data: User[]; nextCursor: string | null; prevCursor: string | null };
    roles: Role[];
    filters: { search: string };
    can: { assign: boolean; impersonate: boolean };
};

function RolesDialog({ user, roles, onClose }: { user: User; roles: Role[]; onClose: () => void }) {
    const { t } = useTranslation('access');
    const { locale } = useLocale();
    const form = useForm({ roles: user.roles.map((role) => role.name) });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(accessUsers.roles.update.url(user.id), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-lg">
                <form onSubmit={submit} className="flex flex-col gap-5">
                    <DialogHeader>
                        <DialogTitle>{t('users.rolesTitle', { name: user.name })}</DialogTitle>
                        <DialogDescription>{t('users.rolesDescription')}</DialogDescription>
                    </DialogHeader>
                    <fieldset className="flex max-h-80 flex-col gap-1 overflow-y-auto">
                        <legend className="sr-only">{t('users.roles')}</legend>
                        {roles.map((role) => (
                            <Label
                                key={role.id}
                                className="flex items-center gap-3 rounded-md px-2 py-2 font-normal hover:bg-muted"
                            >
                                <Checkbox
                                    checked={form.data.roles.includes(role.name)}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'roles',
                                            checked
                                                ? [...form.data.roles, role.name]
                                                : form.data.roles.filter(
                                                      (name) => name !== role.name,
                                                  ),
                                        )
                                    }
                                />
                                <span className="flex-1">
                                    {role.label[locale] ?? role.label.en ?? role.name}
                                </span>
                            </Label>
                        ))}
                    </fieldset>
                    {'error' in form.errors ? (
                        <p className="text-small text-danger">
                            {(form.errors as Record<string, string>).error}
                        </p>
                    ) : null}
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>
                            {t('common:actions.cancel')}
                        </Button>
                        <Button type="submit" loading={form.processing}>
                            {t('users.saveRoles')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function UsersIndex({ users, roles, filters, can }: Props) {
    const { t } = useTranslation('access');
    const { locale } = useLocale();
    const getInitials = useInitials();
    const [search, setSearch] = useState(filters.search);
    const [editing, setEditing] = useState<User | null>(null);

    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        router.get(accessUsers.index.url(), search ? { search } : {}, {
            preserveState: true,
            replace: true,
        });
    };

    const page = (cursor: string | null) =>
        router.get(
            accessUsers.index.url(),
            { ...(search ? { search } : {}), cursor },
            { preserveState: true, preserveScroll: true },
        );

    return (
        <>
            <Head title={t('users.pageTitle')} />
            <PageHeader title={t('title')} description={t('description')} />
            <AccessTabs />

            <form onSubmit={submitSearch} role="search" className="relative mb-4 max-w-sm">
                <Search
                    aria-hidden
                    className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    type="search"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    placeholder={t('users.search')}
                    aria-label={t('users.search')}
                    className="ps-9"
                />
            </form>

            {users.data.length === 0 ? (
                <PageState kind="no-results" title={t('users.empty')} />
            ) : (
                <ul className="flex flex-col gap-2 md:gap-0 md:overflow-hidden md:rounded-lg md:border md:border-border md:bg-card">
                    {users.data.map((user) => (
                        <li
                            key={user.id}
                            className="flex flex-col gap-3 rounded-lg bg-card p-4 shadow-sm md:flex-row md:items-center md:rounded-none md:border-b md:border-border md:shadow-none md:last:border-b-0"
                        >
                            <div className="flex min-w-0 flex-1 items-center gap-3">
                                <Avatar className="size-9">
                                    <AvatarFallback className="bg-secondary text-caption font-semibold text-secondary-foreground">
                                        {getInitials(user.name)}
                                    </AvatarFallback>
                                </Avatar>
                                <div className="flex min-w-0 flex-col">
                                    <span className="flex items-center gap-2 truncate text-sm font-medium">
                                        {user.name}
                                        {user.twoFactorEnabled ? (
                                            <ShieldCheck
                                                aria-label={t('users.twoFactorOn')}
                                                className="size-4 text-success"
                                            />
                                        ) : null}
                                    </span>
                                    <bdi
                                        dir="ltr"
                                        className="truncate text-caption text-muted-foreground"
                                    >
                                        {user.email}
                                    </bdi>
                                </div>
                            </div>
                            <div className="flex flex-wrap items-center gap-1.5 md:w-72">
                                {user.roles.map((role) => (
                                    <Badge key={role.name} variant="secondary">
                                        {role.label[locale] ?? role.label.en ?? role.name}
                                    </Badge>
                                ))}
                                {user.status !== 'active' ? (
                                    <Badge variant="outline">
                                        {t(`users.statuses.${user.status}`)}
                                    </Badge>
                                ) : null}
                            </div>
                            <span className="text-caption text-muted-foreground md:w-40">
                                {user.lastLoginAt
                                    ? formatDate(user.lastLoginAt, locale, { dateStyle: 'medium' })
                                    : t('users.never')}
                            </span>
                            <div className="flex gap-1">
                                <Button variant="ghost" size="icon-sm" asChild>
                                    <Link
                                        href={accessUsers.preview(user.id)}
                                        aria-label={`${t('users.preview')} ${user.name}`}
                                    >
                                        <Eye aria-hidden />
                                    </Link>
                                </Button>
                                {can.assign ? (
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        onClick={() => setEditing(user)}
                                        aria-label={`${t('users.editRoles')}: ${user.name}`}
                                    >
                                        <UserCog aria-hidden />
                                    </Button>
                                ) : null}
                                {can.impersonate ? (
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        onClick={() =>
                                            router.post(accessImpersonate.start.url(user.id))
                                        }
                                        aria-label={`${t('users.impersonate')} ${user.name}`}
                                    >
                                        <LogIn aria-hidden className="rtl:-scale-x-100" />
                                    </Button>
                                ) : null}
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            {users.prevCursor || users.nextCursor ? (
                <div className="mt-4 flex justify-end gap-2">
                    <Button
                        variant="outline"
                        disabled={!users.prevCursor}
                        onClick={() => page(users.prevCursor)}
                    >
                        {t('users.previous')}
                    </Button>
                    <Button
                        variant="outline"
                        disabled={!users.nextCursor}
                        onClick={() => page(users.nextCursor)}
                    >
                        {t('users.next')}
                    </Button>
                </div>
            ) : null}

            {editing ? (
                <RolesDialog user={editing} roles={roles} onClose={() => setEditing(null)} />
            ) : null}
        </>
    );
}
