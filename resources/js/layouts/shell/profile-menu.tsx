import { Link, router, usePage } from '@inertiajs/react';
import { LogOut, Monitor, Moon, Settings, ShieldCheck, SlidersHorizontal, Sun } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAppearance } from '@/hooks/use-appearance';
import { useInitials } from '@/hooks/use-initials';
import { logout } from '@/routes';
import preferences from '@/routes/preferences';
import profile from '@/routes/profile';
import security from '@/routes/security';

export function ProfileMenu() {
    const { t } = useTranslation('shell');
    const { auth } = usePage().props;
    const getInitials = useInitials();
    const { appearance, updateAppearance } = useAppearance();

    if (!auth) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="rounded-full"
                    aria-label={t('profile')}
                >
                    <Avatar className="size-8">
                        <AvatarFallback className="bg-secondary text-caption font-semibold text-secondary-foreground">
                            {getInitials(auth.user.name)}
                        </AvatarFallback>
                    </Avatar>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-64">
                <DropdownMenuLabel className="flex flex-col gap-0.5 font-normal">
                    <span className="truncate text-sm font-medium text-foreground">
                        {auth.user.name}
                    </span>
                    <bdi className="truncate text-caption text-muted-foreground">
                        {auth.user.email}
                    </bdi>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link href={profile.edit()}>
                        <Settings aria-hidden /> {t('menu.profile')}
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link href={security.edit()}>
                        <ShieldCheck aria-hidden /> {t('menu.security')}
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link href={preferences.edit()}>
                        <SlidersHorizontal aria-hidden /> {t('menu.preferences')}
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuLabel className="text-caption font-normal text-muted-foreground">
                    {t('menu.appearance')}
                </DropdownMenuLabel>
                <DropdownMenuRadioGroup
                    value={appearance}
                    onValueChange={(value) => updateAppearance(value as typeof appearance)}
                >
                    <DropdownMenuRadioItem value="light">
                        <Sun aria-hidden /> {t('menu.light')}
                    </DropdownMenuRadioItem>
                    <DropdownMenuRadioItem value="dark">
                        <Moon aria-hidden /> {t('menu.dark')}
                    </DropdownMenuRadioItem>
                    <DropdownMenuRadioItem value="system">
                        <Monitor aria-hidden /> {t('menu.system')}
                    </DropdownMenuRadioItem>
                </DropdownMenuRadioGroup>
                <DropdownMenuSeparator />
                <DropdownMenuItem onSelect={() => router.post(logout.url())}>
                    <LogOut aria-hidden className="rtl:-scale-x-100" /> {t('menu.signOut')}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
