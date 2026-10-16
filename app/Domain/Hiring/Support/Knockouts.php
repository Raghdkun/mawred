<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Support;

use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Forms\Support\FormSchema;
use App\Support\Rules\RuleError;
use App\Support\Rules\Rules;

/**
 * Screening knock-outs (D81): each is a `mawred.rule/v1` condition over one answer; when it holds,
 * the application is flagged with that question. Knock-outs flag, they never reject on their own.
 */
final class Knockouts
{
    /**
     * Problems with a posting's knock-outs against its form.
     *
     * @param  list<array{field: string, rule: mixed}>  $knockouts
     * @param  array<mixed>  $schema
     * @return array<string, string>
     */
    public static function problems(array $knockouts, array $schema): array
    {
        $fields = FormSchema::fieldsByKey($schema);
        $problems = [];

        foreach ($knockouts as $i => $knockout) {
            if (! isset($fields[$knockout['field']])) {
                $problems["knockouts.{$i}.field"] = __('This question is not on the application form.');

                continue;
            }

            foreach (Rules::check($knockout['rule'], ['field']) as $problem) {
                $problems["knockouts.{$i}.rule"] = $problem;
            }

            foreach (Rules::refs($knockout['rule']) as $ref) {
                if ($ref !== "field.{$knockout['field']}") {
                    $problems["knockouts.{$i}.rule"] = __('A knock-out may only check its own question.');
                }
            }
        }

        return $problems;
    }

    /**
     * The questions an applicant was knocked out on, with their labels in both languages.
     *
     * @param  list<array{field: string, rule: mixed}>  $knockouts
     * @param  array<mixed>  $schema
     * @param  array<string, mixed>  $answers
     * @return list<array{field: string, label: array{en: string, ar: string}}>
     */
    public static function failed(array $knockouts, array $schema, array $answers): array
    {
        $fields = FormSchema::fieldsByKey($schema);
        $failed = [];

        foreach ($knockouts as $knockout) {
            try {
                $hit = Rules::truthy(Rules::evaluate($knockout['rule'], ['field' => $answers, 'requester' => null, 'ctx' => []]));
            } catch (RuleError) {
                $hit = false;
            }

            if ($hit) {
                $label = $fields[$knockout['field']]['label'] ?? null;
                $failed[] = ['field' => $knockout['field'], 'label' => [
                    'en' => FieldSpec::text($label, 'en', $knockout['field']),
                    'ar' => FieldSpec::text($label, 'ar', $knockout['field']),
                ]];
            }
        }

        return $failed;
    }
}
