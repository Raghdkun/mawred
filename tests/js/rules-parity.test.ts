import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';
import { resolveForm } from '@/features/forms/resolve';
import { type FormField } from '@/features/forms/schema';
import { evaluate, type RuleContext } from '@/lib/rules';

/** The same fixtures run in Pest (tests/Unit/Support/RulesParityTest.php); CI fails on drift. */
type Fixture = {
    context: RuleContext;
    cases: Array<{
        name: string;
        rule: unknown;
        context?: RuleContext;
        expected?: unknown;
        error?: boolean;
    }>;
};

const dir = join(process.cwd(), 'tests/fixtures/rules');

for (const file of readdirSync(dir).filter((name) => name.endsWith('.json'))) {
    const fixture = JSON.parse(readFileSync(join(dir, file), 'utf8')) as Fixture;

    describe(`rules parity: ${file}`, () => {
        for (const testCase of fixture.cases) {
            it(testCase.name, () => {
                const run = () => evaluate(testCase.rule, testCase.context ?? fixture.context);

                if (testCase.error) {
                    expect(run).toThrow();
                } else {
                    expect(run()).toEqual(testCase.expected);
                }
            });
        }
    });
}

type ResolutionFixture = {
    context: RuleContext;
    fields: FormField[];
    cases: Array<{
        name: string;
        input: Record<string, unknown>;
        expected: { values: Record<string, unknown>; hidden: string[] };
    }>;
};

const resolution = JSON.parse(
    readFileSync(join(process.cwd(), 'tests/fixtures/forms/resolution.json'), 'utf8'),
) as ResolutionFixture;

describe('form resolution parity', () => {
    for (const testCase of resolution.cases) {
        it(testCase.name, () => {
            expect(resolveForm(resolution.fields, testCase.input, resolution.context)).toEqual(
                testCase.expected,
            );
        });
    }
});
