import { describe, expect, it } from 'vitest';
import { forServer } from './submit';

describe('forServer', () => {
    it('sends upload tokens for file answers and leaves the rest alone', () => {
        const answers = {
            cv: [
                {
                    id: '01jcv0000000000000000000aa',
                    name: 'cv.pdf',
                    size: 10,
                    mime: 'application/pdf',
                },
            ],
            kept: ['01jkept000000000000000000a'],
            name: 'Lina',
        };

        expect(
            forServer(answers, [
                { key: 'cv', type: 'file' },
                { key: 'kept', type: 'file' },
                { key: 'name', type: 'text' },
            ]),
        ).toEqual({
            cv: ['01jcv0000000000000000000aa'],
            kept: ['01jkept000000000000000000a'],
            name: 'Lina',
        });
        expect(answers.cv[0]).toHaveProperty('name', 'cv.pdf');
    });
});
