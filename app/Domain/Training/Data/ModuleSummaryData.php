<?php

declare(strict_types=1);

namespace App\Domain\Training\Data;

use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Training\Enums\ModuleKind;
use App\Domain\Training\Models\CourseModule;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A module in a course's outline (handoff 33), with the viewer's progress on it. */
#[TypeScript]
final class ModuleSummaryData extends Data
{
    /**
     * @param  array{passMark: int, attemptsAllowed: int, attemptsUsed: int, bestScore: int|null, passed: bool}|null  $quiz
     */
    public function __construct(
        public string $id,
        public string $kind,
        public string $title,
        public ?int $durationMinutes,
        public bool $required,
        public bool $done,
        public ?array $quiz,
    ) {}

    /**
     * @param  list<array{score: int, passed: bool}>  $attempts
     */
    public static function fromModule(CourseModule $module, string $locale, bool $done, array $attempts = []): self
    {
        $scores = array_column($attempts, 'score');

        return new self(
            $module->ulid,
            $module->kind->value,
            FieldSpec::text($module->getTranslations('title'), $locale),
            $module->duration_minutes,
            $module->required,
            $done,
            $module->kind === ModuleKind::Quiz ? [
                'passMark' => $module->passMark(),
                'attemptsAllowed' => $module->attemptsAllowed(),
                'attemptsUsed' => count($attempts),
                'bestScore' => $scores === [] ? null : max($scores),
                'passed' => in_array(true, array_column($attempts, 'passed'), true),
            ] : null,
        );
    }
}
