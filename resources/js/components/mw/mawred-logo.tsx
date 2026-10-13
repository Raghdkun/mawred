import lockupArColor from '../../../../design/brand/logo/color/mawred-lockup-h-ar.svg?url';
import lockupEnColor from '../../../../design/brand/logo/color/mawred-lockup-h-en.svg?url';
import symbolColor from '../../../../design/brand/logo/color/mawred-symbol.svg?url';
import lockupArDark from '../../../../design/brand/logo/dark/mawred-lockup-h-ar.svg?url';
import lockupEnDark from '../../../../design/brand/logo/dark/mawred-lockup-h-en.svg?url';
import symbolDark from '../../../../design/brand/logo/dark/mawred-symbol.svg?url';
import { useLocale } from '@/hooks/use-locale';
import { cn } from '@/lib/utils';

/** The Mawred mark from design/brand/logo (never redrawn). Logos never mirror. */
export function MawredLogo({
    variant = 'lockup',
    className,
}: {
    variant?: 'lockup' | 'symbol';
    className?: string;
}) {
    const { locale } = useLocale();
    const light =
        variant === 'symbol' ? symbolColor : locale === 'ar' ? lockupArColor : lockupEnColor;
    const dark = variant === 'symbol' ? symbolDark : locale === 'ar' ? lockupArDark : lockupEnDark;
    const alt = locale === 'ar' ? 'مورد' : 'Mawred';

    // `className` sizes/places the wrapper; the images only switch with the theme, so responsive
    // display classes from callers can't collide with the light/dark toggle.
    return (
        <span className={cn('inline-flex shrink-0', className)}>
            <img src={light} alt={alt} className="block h-full w-auto dark:hidden" />
            <img src={dark} alt="" aria-hidden className="hidden h-full w-auto dark:block" />
        </span>
    );
}
