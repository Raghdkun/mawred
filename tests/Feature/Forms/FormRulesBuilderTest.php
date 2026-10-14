<?php

declare(strict_types=1);

use App\Domain\Forms\Support\FormRulesBuilder;
use App\Domain\Organization\Models\Branch;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->context = ['requester' => ['branch' => 'b1', 'currency' => 'SAR'], 'ctx' => ['today' => '2026-10-15', 'calendar' => ['weekend' => [5, 6], 'holidays' => []]]];
    $this->schema = mfs([
        field('kind', 'radio', ['required' => true, 'options' => [['value' => 'trip', 'label' => ['en' => 'Trip']], ['value' => 'other', 'label' => ['en' => 'Other']]]]),
        field('destination', 'text', ['required' => true, 'visible' => ['op' => 'eq', 'args' => [['ref' => 'field.kind'], 'trip']]]),
        field('explain', 'textarea', ['required' => ['op' => 'eq', 'args' => [['ref' => 'field.kind'], 'other']]]),
        field('dates', 'date_range', ['required' => true, 'validation' => ['min' => ['ref' => 'ctx.today'], 'maxDays' => 10]]),
        field('working_days', 'calculated', ['calc' => ['op' => 'working_days_between', 'args' => [['ref' => 'field.dates.from'], ['ref' => 'field.dates.to']]]]),
        field('budget', 'money', ['validation' => ['max' => 500000]]),
        field('phone', 'phone'),
        field('agree', 'boolean', ['required' => true]),
        field('tags', 'checkboxes', ['options' => [['value' => 'x', 'label' => ['en' => 'X']], ['value' => 'y', 'label' => ['en' => 'Y']]], 'validation' => ['maxItems' => 1]]),
    ]);
});

function validateForm(object $test, array $input): array
{
    return resolve(FormRulesBuilder::class)->validate($test->schema, $input, $test->context);
}

it('recomputes calculated fields and drops hidden ones', function (): void {
    $data = validateForm($this, [
        'kind' => 'other', 'destination' => 'Ignored because hidden', 'explain' => '  Family  ',
        'dates' => ['from' => '2026-10-15', 'to' => '2026-10-21'], 'working_days' => 999,
        'budget' => ['amount_minor' => 120000, 'currency' => 'SAR'], 'agree' => true, 'tags' => ['y'],
    ]);

    expect($data)->not->toHaveKey('destination')
        ->and($data['working_days'])->toBe(5)
        ->and($data['explain'])->toBe('Family')
        ->and($data['budget'])->toBe(['amount_minor' => 120000, 'currency' => 'SAR'])
        ->and($data['phone'])->toBeNull();
});

it('enforces conditional required, bounds and option lists', function (): void {
    try {
        validateForm($this, [
            'kind' => 'trip', 'dates' => ['from' => '2026-10-10', 'to' => '2026-10-30'],
            'budget' => ['amount_minor' => 900000, 'currency' => 'USD'], 'phone' => '0501234567',
            'agree' => false, 'tags' => ['x', 'y'],
        ]);
        $this->fail('Expected validation to fail.');
    } catch (ValidationException $e) {
        expect(array_keys($e->errors()))->toEqualCanonicalizing([
            'destination', 'dates', 'dates.from', 'budget.amount_minor', 'budget.currency', 'phone', 'agree', 'tags',
        ]);
    }
});

it('checks directory picker values exist', function (): void {
    $branch = Branch::factory()->create();
    $schema = mfs([field('where', 'branch', ['required' => true])]);
    $builder = resolve(FormRulesBuilder::class);

    expect($builder->validate($schema, ['where' => $branch->ulid], $this->context))->toBe(['where' => $branch->ulid])
        ->and(fn () => $builder->validate($schema, ['where' => '01aaaaaaaaaaaaaaaaaaaaaaaa'], $this->context))->toThrow(ValidationException::class);
});
