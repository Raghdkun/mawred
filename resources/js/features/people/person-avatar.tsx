import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';

export function PersonAvatar({ name, className }: { name: string; className?: string }) {
    const getInitials = useInitials();

    return (
        <Avatar className={cn('size-9', className)}>
            <AvatarFallback className="bg-secondary text-caption font-semibold text-secondary-foreground">
                {getInitials(name)}
            </AvatarFallback>
        </Avatar>
    );
}
