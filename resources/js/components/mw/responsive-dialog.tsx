import { type ReactNode } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Drawer,
    DrawerContent,
    DrawerDescription,
    DrawerFooter,
    DrawerHeader,
    DrawerTitle,
} from '@/components/ui/drawer';
import { useIsMobile } from '@/hooks/use-mobile';
import { cn } from '@/lib/utils';

/**
 * A dialog on tablet and desktop, a bottom sheet on phones (handoff global rules: bottom sheets
 * replace dialogs and popovers at 390). Content scrolls; the footer stays reachable.
 */
export function ResponsiveDialog({
    open,
    onOpenChange,
    title,
    description,
    children,
    footer,
    className,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    children: ReactNode;
    footer?: ReactNode;
    className?: string;
}) {
    const isMobile = useIsMobile();

    if (isMobile) {
        return (
            <Drawer open={open} onOpenChange={onOpenChange}>
                <DrawerContent className="max-h-[92dvh]">
                    <DrawerHeader className="text-start">
                        <DrawerTitle>{title}</DrawerTitle>
                        {description ? <DrawerDescription>{description}</DrawerDescription> : null}
                    </DrawerHeader>
                    <div className="overflow-y-auto px-4 pb-2">{children}</div>
                    {footer ? <DrawerFooter>{footer}</DrawerFooter> : null}
                </DrawerContent>
            </Drawer>
        );
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className={cn('flex max-h-[90dvh] flex-col sm:max-w-xl', className)}>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    {description ? <DialogDescription>{description}</DialogDescription> : null}
                </DialogHeader>
                <div className="-mx-6 overflow-y-auto px-6 py-1">{children}</div>
                {footer ? <DialogFooter>{footer}</DialogFooter> : null}
            </DialogContent>
        </Dialog>
    );
}
