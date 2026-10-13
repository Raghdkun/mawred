import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { extendTailwindMerge } from 'tailwind-merge';

/**
 * tailwind-merge must know the token type scale (tokens.css `--text-*`), otherwise `text-body`
 * is mistaken for a colour and silently removes `text-primary-foreground` and friends.
 */
const twMerge = extendTailwindMerge({
    extend: {
        theme: {
            text: [
                'display',
                'h1',
                'h2',
                'h3',
                'h4',
                'body-lg',
                'body',
                'small',
                'caption',
                'overline',
            ],
        },
    },
});

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}
