import { describe, expect, it } from 'vitest';
import { cn } from './utils';

describe('cn', () => {
    it('keeps a colour class next to a token font size', () => {
        expect(cn('text-primary-foreground', 'text-body')).toBe(
            'text-primary-foreground text-body',
        );
    });

    it('still dedupes conflicting font sizes and colours', () => {
        expect(cn('text-small', 'text-body')).toBe('text-body');
        expect(cn('text-foreground', 'text-danger')).toBe('text-danger');
    });
});
