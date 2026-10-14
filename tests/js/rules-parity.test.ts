import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';
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
