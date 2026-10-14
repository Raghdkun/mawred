<?php

declare(strict_types=1);

use App\Domain\Forms\Support\FormSchema;

it('accepts a well-formed schema', function (): void {
    $schema = mfs([
        field('reason', 'textarea', ['validation' => ['maxLength' => 500]]),
        field('kind', 'select', ['options' => [['value' => 'a', 'label' => ['en' => 'A']], ['value' => 'b', 'label' => ['en' => 'B']]]]),
        field('details', 'text', ['visible' => ['op' => 'eq', 'args' => [['ref' => 'field.kind'], 'b']], 'required' => true]),
        field('nights', 'calculated', ['calc' => ['op' => 'days_between', 'args' => [['ref' => 'ctx.today'], '2026-12-31']]]),
        field('cv', 'file', ['validation' => ['maxFiles' => 2, 'accept' => ['pdf', 'word']]]),
        field('intro', 'note'),
    ]);

    expect(FormSchema::problems($schema))->toBe([]);
});

it('reports problems by path', function (): void {
    $problems = FormSchema::problems(mfs([
        field('Bad Key', 'text'),
        ['key' => 'no_label', 'type' => 'text'],
        field('dup', 'text'),
        field('dup', 'number', ['validation' => ['min' => 10, 'max' => 1]]),
        field('pick', 'select', ['options' => []]),
        field('mystery', 'slider'),
        field('total', 'calculated'),
        field('cond', 'text', ['visible' => ['op' => 'eq', 'args' => [['ref' => 'field.ghost'], 1]]]),
        field('files', 'file', ['validation' => ['maxFiles' => 50, 'accept' => ['exe']]]),
    ]));

    $at = fn (int $i, string $rest) => "pages.0.sections.0.fields.{$i}.{$rest}";

    expect($problems)->toHaveKeys([
        $at(0, 'key'), $at(1, 'label'), $at(3, 'key'), $at(3, 'validation.max'), $at(4, 'options'),
        $at(5, 'type'), $at(6, 'calc'), $at(7, 'visible'), $at(8, 'validation.maxFiles'), $at(8, 'validation.accept'),
    ]);
});

it('keeps directory pickers and requester data off public forms', function (): void {
    $problems = FormSchema::problems(mfs([
        field('colleague', 'employee'),
        field('note', 'text', ['visible' => ['op' => 'eq', 'args' => [['ref' => 'requester.branch'], 'x']]]),
        field('fee', 'money'),
    ]), public: true);

    expect($problems)->toHaveKeys(['pages.0.sections.0.fields.0.type', 'pages.0.sections.0.fields.1.visible', 'pages.0.sections.0.fields.2.currency']);
});

it('refuses documents that are not MFS v1', function (): void {
    expect(FormSchema::problems(['format' => 'other', 'pages' => []]))->toHaveKeys(['format', 'pages']);
});
