<?php

declare(strict_types=1);

namespace App\Domain\Training\Data;

use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Training\Enums\ModuleKind;
use App\Domain\Training\Models\CourseModule;
use App\Domain\Training\Models\QuizAttempt;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A module as the learner works through it (handoff 34). Quiz questions come without the right
 * answers; options keep their authored order in both languages (D102). The last attempt's
 * per-question result is shown once it is submitted.
 */
#[TypeScript]
final class ModuleData extends Data
{
    /**
     * @param  array{passMark: int, attemptsAllowed: int, attemptsUsed: int, passed: bool, bestScore: int|null, questions: list<array{id: string, type: string, prompt: string, points: int, options: list<array{id: string, label: string}>}>, last: array{score: int, passed: bool, correct: list<string>}|null}|null  $quiz
     */
    public function __construct(
        public string $id,
        public string $kind,
        public string $title,
        public ?string $body,
        public ?string $url,
        public ?string $fileName,
        public ?int $durationMinutes,
        public bool $required,
        public bool $done,
        public ?array $quiz,
    ) {}

    /**
     * @param  list<QuizAttempt>  $attempts  oldest first
     */
    public static function fromModule(CourseModule $module, string $locale, bool $done, array $attempts = []): self
    {
        $body = FieldSpec::text($module->getTranslations('body'), $locale);
        $quiz = null;

        if ($module->kind === ModuleKind::Quiz) {
            $questions = [];
            $correctIds = [];

            foreach ($module->questions as $question) {
                $questions[] = [
                    'id' => $question->ulid,
                    'type' => $question->type->value,
                    'prompt' => FieldSpec::text($question->getTranslations('prompt'), $locale),
                    'points' => $question->points,
                    'options' => array_map(fn (array $option): array => ['id' => $option['id'], 'label' => FieldSpec::text($option['label'], $locale)], $question->options),
                ];
                $correctIds[$question->ulid] = array_column(array_filter($question->options, fn (array $o): bool => $o['correct']), 'id');
            }

            $last = $attempts === [] ? null : $attempts[array_key_last($attempts)];
            $correct = [];

            if ($last instanceof QuizAttempt) {
                foreach ($correctIds as $id => $right) {
                    $chosen = $last->answers[$id] ?? [];
                    sort($chosen);
                    sort($right);

                    if ($chosen !== [] && $chosen === $right) {
                        $correct[] = $id;
                    }
                }
            }

            $scores = array_map(fn (QuizAttempt $a): int => $a->score, $attempts);
            $quiz = [
                'passMark' => $module->passMark(),
                'attemptsAllowed' => $module->attemptsAllowed(),
                'attemptsUsed' => count($attempts),
                'passed' => array_filter($attempts, fn (QuizAttempt $a): bool => $a->passed) !== [],
                'bestScore' => $scores === [] ? null : max($scores),
                'questions' => $questions,
                'last' => $last instanceof QuizAttempt ? ['score' => $last->score, 'passed' => $last->passed, 'correct' => $correct] : null,
            ];
        }

        return new self(
            $module->ulid,
            $module->kind->value,
            FieldSpec::text($module->getTranslations('title'), $locale),
            $body === '' ? null : $body,
            $module->url,
            $module->file_name,
            $module->duration_minutes,
            $module->required,
            $done,
            $quiz,
        );
    }
}
