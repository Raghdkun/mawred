import { ChevronDown } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { type CellValue, nextValue, SCOPED_OPTIONS, UNSCOPED_CYCLE } from './scopes';

/** DSData `.lvl` colours: All = primary, Team/Department/Branch = soft blue, Own = muted, None = dashed. */
function styleFor(value: CellValue): string {
    switch (value) {
        case 'all':
        case 'on':
            return 'bg-primary text-primary-foreground';
        case 'branch':
        case 'department':
        case 'team_tree':
        case 'direct_reports':
            return 'bg-secondary text-secondary-foreground';
        case 'own':
            return 'bg-muted text-text-secondary';
        default:
            return 'border border-dashed border-input-hover text-muted-foreground';
    }
}

export function ScopeCell({
    value,
    scoped,
    overridden,
    readOnly,
    moduleLabel,
    actionLabel,
    onChange,
}: {
    value: CellValue;
    scoped: boolean;
    overridden: boolean;
    readOnly: boolean;
    moduleLabel: string;
    actionLabel: string;
    onChange: (value: CellValue) => void;
}) {
    const { t } = useTranslation('access');
    const label = t(`scopes.${value}`);
    const fullName = t(`scopeNames.${value}`);
    const options = scoped ? SCOPED_OPTIONS : UNSCOPED_CYCLE;

    const pill = (
        <span
            className={cn(
                'inline-flex h-[26px] min-w-[72px] items-center justify-center gap-1 rounded-[7px] px-2 text-caption font-medium',
                styleFor(value),
                overridden && 'shadow-[inset_0_0_0_1.5px_var(--status-escalated-fg)]',
            )}
        >
            {label}
            {value === 'direct_reports' ? <sup aria-hidden>1</sup> : null}
            {overridden ? (
                <span aria-hidden className="size-1.5 rounded-full bg-status-escalated-fg" />
            ) : null}
        </span>
    );

    const aria = `${t('matrix.cellLabel', { module: moduleLabel, action: actionLabel, scope: fullName })}${overridden ? `. ${t('matrix.overridden')}` : ''}`;

    if (readOnly) {
        return (
            <span role="img" aria-label={aria} title={fullName}>
                {pill}
            </span>
        );
    }

    return (
        <div className="inline-flex items-center">
            <button
                type="button"
                onClick={() => onChange(nextValue(value, scoped))}
                aria-label={aria}
                title={fullName}
                className="rounded-[7px] focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
                {pill}
            </button>
            {scoped ? (
                <DropdownMenu>
                    <DropdownMenuTrigger
                        aria-label={`${moduleLabel} · ${actionLabel}`}
                        className="ms-0.5 inline-flex size-6 items-center justify-center rounded-sm text-muted-foreground hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <ChevronDown aria-hidden className="size-3.5" />
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start">
                        <DropdownMenuRadioGroup
                            value={value}
                            onValueChange={(next) => onChange(next as CellValue)}
                        >
                            {options.map((option) => (
                                <DropdownMenuRadioItem key={option} value={option}>
                                    {t(`scopeNames.${option}`)}
                                </DropdownMenuRadioItem>
                            ))}
                        </DropdownMenuRadioGroup>
                    </DropdownMenuContent>
                </DropdownMenu>
            ) : null}
        </div>
    );
}
