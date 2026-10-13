import * as React from 'react';
import { cva, type VariantProps } from 'class-variance-authority';
import { Slot } from 'radix-ui';
import { cn } from '@/lib/utils';
import { Spinner } from '@/components/ui/spinner';

/**
 * Button per the design system (design/canvas-source/DSActions.dc.html): primary, secondary,
 * outline, ghost, destructive; sm 32 / md 40 / lg 48 px; press ripple from the pointer
 * (Motion spec: 450 ms, scale .25 → 1, opacity .35 → 0; pressed tint only when motion is reduced).
 */
const buttonVariants = cva(
    "relative inline-flex shrink-0 cursor-pointer items-center justify-center gap-2 overflow-hidden rounded-md text-sm leading-none font-medium whitespace-nowrap transition-[background-color,border-color,color,box-shadow] duration-150 ease-soft outline-none select-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background active:scale-[0.98] motion-reduce:active:scale-100 disabled:pointer-events-none disabled:border-transparent disabled:bg-disabled disabled:text-disabled-foreground aria-invalid:border-error-border aria-invalid:bg-danger-bg aria-invalid:text-danger [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4",
    {
        variants: {
            variant: {
                default: 'bg-primary text-primary-foreground hover:bg-primary-hover active:bg-primary-active',
                secondary: 'bg-secondary text-secondary-foreground hover:bg-secondary-hover active:bg-secondary-hover',
                outline: 'border border-input bg-card text-foreground hover:border-input-hover hover:bg-muted active:bg-surface-sunken',
                ghost: 'bg-transparent text-foreground hover:bg-muted active:bg-surface-sunken',
                destructive: 'bg-destructive text-destructive-foreground hover:bg-destructive-hover active:bg-destructive-hover',
                link: 'h-auto rounded-sm px-0 text-link underline-offset-4 hover:underline active:scale-100',
            },
            size: {
                default: 'h-10 px-4',
                sm: 'h-8 rounded-sm px-3 text-small',
                lg: 'h-12 px-[22px] text-body',
                icon: 'size-10 text-text-secondary',
                'icon-sm': 'size-8 rounded-sm text-text-secondary',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
        },
    },
);

type ButtonProps = React.ComponentProps<'button'> &
    VariantProps<typeof buttonVariants> & {
        asChild?: boolean;
        loading?: boolean;
    };

function Ripple({ x, y, onDone }: { x: number; y: number; onDone: () => void }) {
    return (
        <span
            aria-hidden
            onAnimationEnd={onDone}
            className="pointer-events-none absolute size-16 animate-press-ripple rounded-full bg-current opacity-0"
            // The pointer position is physical, so the ripple is placed physically in LTR and RTL.
            style={{ left: x, top: y }}
        />
    );
}

function Button({ className, variant, size, asChild = false, loading = false, disabled, children, onPointerDown, ...props }: ButtonProps) {
    const [ripples, setRipples] = React.useState<Array<{ id: number; x: number; y: number }>>([]);
    const Comp = asChild ? Slot.Root : 'button';

    const handlePointerDown = (event: React.PointerEvent<HTMLButtonElement>) => {
        onPointerDown?.(event);

        if (variant === 'link' || asChild) {
            return;
        }

        const rect = event.currentTarget.getBoundingClientRect();

        setRipples((current) => [...current, { id: event.timeStamp, x: event.clientX - rect.left, y: event.clientY - rect.top }]);
    };

    return (
        <Comp
            data-slot="button"
            data-variant={variant}
            className={cn(buttonVariants({ variant, size, className }), loading && 'cursor-progress opacity-90')}
            disabled={disabled || loading}
            aria-busy={loading || undefined}
            onPointerDown={handlePointerDown}
            {...props}
        >
            {asChild ? (
                children
            ) : (
                <>
                    {loading ? <Spinner /> : null}
                    {children}
                    {ripples.map((ripple) => (
                        <Ripple
                            key={ripple.id}
                            x={ripple.x}
                            y={ripple.y}
                            onDone={() => setRipples((current) => current.filter((r) => r.id !== ripple.id))}
                        />
                    ))}
                </>
            )}
        </Comp>
    );
}

export { Button, buttonVariants };
