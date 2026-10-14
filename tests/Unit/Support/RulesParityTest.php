<?php

declare(strict_types=1);

use App\Domain\Forms\Support\FormResolution;
use App\Support\Rules\RuleError;
use App\Support\Rules\Rules;

/**
 * The same fixtures run in Vitest (tests/js/rules-parity.test.ts); CI fails on drift.
 *
 * @return array<string, array{0: mixed, 1: array<string, mixed>, 2: mixed, 3: bool}>
 */
function ruleFixtures(): array
{
    $cases = [];

    foreach (glob(dirname(__DIR__, 2).'/fixtures/rules/*.json') ?: [] as $file) {
        /** @var array{context: array<string, mixed>, cases: list<array{name: string, rule: mixed, context?: array<string, mixed>, expected?: mixed, error?: bool}>} $fixture */
        $fixture = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);

        foreach ($fixture['cases'] as $case) {
            $cases[basename($file, '.json').': '.$case['name']] = [$case['rule'], $case['context'] ?? $fixture['context'], $case['expected'] ?? null, $case['error'] ?? false];
        }
    }

    return $cases;
}

it('matches the shared fixture', function (mixed $rule, array $context, mixed $expected, bool $error): void {
    if ($error) {
        expect(fn () => Rules::evaluate($rule, $context))->toThrow(RuleError::class);

        return;
    }

    expect(Rules::evaluate($rule, $context))->toEqual($expected);
})->with(ruleFixtures());

it('checks rules before they are saved', function (): void {
    expect(Rules::check(['op' => 'eq', 'args' => [['ref' => 'field.a'], 1]]))->toBe([])
        ->and(Rules::check(['op' => 'eq', 'args' => [['ref' => 'secrets.key'], 1]]))->toBe(['Unknown reference "secrets.key".'])
        ->and(Rules::check(['op' => 'pow', 'args' => [1, 2]]))->toBe(['Unknown operator "pow".'])
        ->and(Rules::refs(['op' => 'all', 'args' => [['op' => 'gt', 'args' => [['ref' => 'field.a'], 1]], ['ref' => 'field.b'], ['ref' => 'field.a']]]))->toBe(['field.a', 'field.b']);
});

it('refuses rules nested too deeply', function (): void {
    $rule = true;

    for ($i = 0; $i < 40; $i++) {
        $rule = ['op' => 'not', 'args' => [$rule]];
    }

    expect(fn () => Rules::evaluate($rule, []))->toThrow(RuleError::class, 'too deeply');
});

it('resolves forms like the client does', function (): void {
    /** @var array{context: array<string, mixed>, fields: list<array<string, mixed>>, cases: list<array{name: string, input: array<string, mixed>, expected: array{values: array<string, mixed>, hidden: list<string>}}>} $fixture */
    $fixture = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/fixtures/forms/resolution.json'), true, flags: JSON_THROW_ON_ERROR);
    $fields = array_column($fixture['fields'], null, 'key');

    foreach ($fixture['cases'] as $case) {
        expect(FormResolution::resolve($fields, $case['input'], $fixture['context']))->toEqual($case['expected'], $case['name']);
    }
});
