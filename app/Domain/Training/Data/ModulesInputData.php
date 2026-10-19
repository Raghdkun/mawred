<?php

declare(strict_types=1);

namespace App\Domain\Training\Data;

use App\Domain\Training\Enums\ModuleKind;
use App\Domain\Training\Enums\QuestionType;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A course's modules in order, with their quiz questions (D98, D102). Rows with an id update
 * that module or question; rows without one are new; anything missing is removed.
 */
#[TypeScript]
final class ModulesInputData extends Data
{
    /**
     * @param  list<array{id?: string|null, kind: string, title: array{en: string, ar?: string|null}, body?: array{en?: string|null, ar?: string|null}|null, url?: string|null, durationMinutes?: int|null, required?: bool, passMark?: int|null, maxAttempts?: int|null, questions?: list<array{id?: string|null, type: string, prompt: array{en: string, ar?: string|null}, points?: int, options: list<array{id?: string|null, label: array{en: string, ar?: string|null}, correct: bool}>}>}>  $modules
     */
    public function __construct(public array $modules) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        $text = fn (int $max): array => ['nullable', 'string', "max:{$max}"];

        return [
            'modules' => ['present', 'array', 'max:60'],
            'modules.*.id' => ['nullable', 'string', 'size:26'],
            'modules.*.kind' => ['required', Rule::enum(ModuleKind::class)],
            'modules.*.title' => ['required', 'array'],
            'modules.*.title.en' => ['required', 'string', 'max:160'],
            'modules.*.title.ar' => $text(160),
            'modules.*.body' => ['nullable', 'array'],
            'modules.*.body.en' => $text(20000),
            'modules.*.body.ar' => $text(20000),
            'modules.*.url' => ['nullable', 'url:https', 'max:500'],
            'modules.*.durationMinutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'modules.*.required' => ['boolean'],
            'modules.*.passMark' => ['nullable', 'integer', 'min:1', 'max:100'],
            'modules.*.maxAttempts' => ['nullable', 'integer', 'min:1', 'max:10'],
            'modules.*.questions' => ['array', 'max:50'],
            'modules.*.questions.*.id' => ['nullable', 'string', 'size:26'],
            'modules.*.questions.*.type' => ['required', Rule::enum(QuestionType::class)],
            'modules.*.questions.*.prompt' => ['required', 'array'],
            'modules.*.questions.*.prompt.en' => ['required', 'string', 'max:1000'],
            'modules.*.questions.*.prompt.ar' => $text(1000),
            'modules.*.questions.*.points' => ['nullable', 'integer', 'min:1', 'max:10'],
            'modules.*.questions.*.options' => ['required', 'array', 'min:2', 'max:8'],
            'modules.*.questions.*.options.*.id' => ['nullable', 'string', 'max:26'],
            'modules.*.questions.*.options.*.label' => ['required', 'array'],
            'modules.*.questions.*.options.*.label.en' => ['required', 'string', 'max:300'],
            'modules.*.questions.*.options.*.label.ar' => $text(300),
            'modules.*.questions.*.options.*.correct' => ['boolean'],
        ];
    }
}
