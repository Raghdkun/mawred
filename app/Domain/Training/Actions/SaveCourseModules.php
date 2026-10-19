<?php

declare(strict_types=1);

namespace App\Domain\Training\Actions;

use App\Domain\Training\Data\ModulesInputData;
use App\Domain\Training\Enums\ModuleKind;
use App\Domain\Training\Enums\QuestionType;
use App\Domain\Training\Jobs\RefreshCourseProgressJob;
use App\Domain\Training\Models\Course;
use App\Domain\Training\Models\CourseModule;
use App\Domain\Training\Models\QuizQuestion;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Saves a course's modules in order with their quiz questions (D98, D102). A video module needs
 * a link and a quiz at least one question; single-choice and true/false questions have exactly
 * one right answer, multiple-choice at least one. Option ids are kept so answers stay valid.
 * Live enrolments are re-checked afterwards, since the set of required modules may change.
 */
final readonly class SaveCourseModules
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(Actor $actor, Course $course, ModulesInputData $input): Course
    {
        $this->validate($input);

        $this->db->transaction(function () use ($actor, $course, $input): void {
            $existing = $course->modules()->get()->keyBy('ulid');
            $kept = [];
            $both = fn (?array $text): ?array => $text === null || ($text['en'] ?? '') === '' ? null : ['en' => $text['en'], 'ar' => ($text['ar'] ?? null) ?: $text['en']];

            foreach ($input->modules as $position => $row) {
                $module = is_string($row['id'] ?? null) ? $existing->get($row['id']) : null;
                $module ??= new CourseModule(['course_id' => $course->id]);
                $kind = ModuleKind::from($row['kind']);
                $quiz = $kind === ModuleKind::Quiz;

                $module->fill([
                    'position' => $position,
                    'kind' => $kind,
                    'title' => $both($row['title']),
                    'body' => $both($row['body'] ?? null),
                    'url' => $kind === ModuleKind::Video ? ($row['url'] ?? null) : null,
                    'duration_minutes' => $row['durationMinutes'] ?? null,
                    'required' => $row['required'] ?? true,
                    'pass_mark' => $quiz ? ($row['passMark'] ?? CourseModule::DEFAULT_PASS_MARK) : null,
                    'max_attempts' => $quiz ? ($row['maxAttempts'] ?? CourseModule::DEFAULT_ATTEMPTS) : null,
                ]);

                if ($kind !== ModuleKind::File && $module->file_path !== null) {
                    Storage::disk(CourseModule::DISK)->delete($module->file_path);
                    $module->forceFill(['file_path' => null, 'file_name' => null]);
                }

                $module->save();
                $kept[] = $module->id;
                $this->saveQuestions($module, $quiz ? ($row['questions'] ?? []) : []);
            }

            foreach ($existing->whereNotIn('id', $kept) as $removed) {
                if ($removed->file_path !== null) {
                    Storage::disk(CourseModule::DISK)->delete($removed->file_path);
                }

                $removed->delete();
            }

            activity('training')->causedBy($actor->user)->performedOn($course)->event('modules')
                ->withProperties(['modules' => count($kept)])->log('Course modules saved');

            dispatch(new RefreshCourseProgressJob($course->id))->afterCommit();
        });

        return $course;
    }

    /**
     * @param  list<array{id?: string|null, type: string, prompt: array{en: string, ar?: string|null}, points?: int, options: list<array{id?: string|null, label: array{en: string, ar?: string|null}, correct: bool}>}>  $questions
     */
    private function saveQuestions(CourseModule $module, array $questions): void
    {
        $existing = $module->questions()->get()->keyBy('ulid');
        $kept = [];

        foreach ($questions as $position => $row) {
            $question = is_string($row['id'] ?? null) ? $existing->get($row['id']) : null;
            $question ??= new QuizQuestion(['course_module_id' => $module->id]);
            $type = QuestionType::from($row['type']);

            $question->fill([
                'position' => $position,
                'type' => $type,
                'prompt' => ['en' => $row['prompt']['en'], 'ar' => ($row['prompt']['ar'] ?? null) ?: $row['prompt']['en']],
                'points' => $row['points'] ?? 1,
                'options' => array_map(fn (array $option): array => [
                    'id' => is_string($option['id'] ?? null) && $option['id'] !== '' ? $option['id'] : Str::lower((string) Str::ulid()),
                    'label' => ['en' => $option['label']['en'], 'ar' => ($option['label']['ar'] ?? null) ?: $option['label']['en']],
                    'correct' => $option['correct'],
                ], $row['options']),
            ])->save();
            $kept[] = $question->id;
        }

        $module->questions()->whereNotIn('id', $kept)->delete();
    }

    private function validate(ModulesInputData $input): void
    {
        $errors = [];

        foreach ($input->modules as $m => $module) {
            $kind = ModuleKind::from($module['kind']);

            if ($kind === ModuleKind::Video && ($module['url'] ?? '') === '') {
                $errors["modules.{$m}.url"] = [__('Add the video’s link.')];
            }

            if ($kind === ModuleKind::Quiz && ($module['questions'] ?? []) === []) {
                $errors["modules.{$m}.questions"] = [__('Add at least one question.')];
            }

            foreach ($kind === ModuleKind::Quiz ? ($module['questions'] ?? []) : [] as $q => $question) {
                $type = QuestionType::from($question['type']);
                $correct = count(array_filter($question['options'], fn (array $o): bool => $o['correct']));

                if ($type === QuestionType::TrueFalse && count($question['options']) !== 2) {
                    $errors["modules.{$m}.questions.{$q}.options"] = [__('A true/false question has exactly two answers.')];
                } elseif ($type === QuestionType::Multiple ? $correct < 1 : $correct !== 1) {
                    $errors["modules.{$m}.questions.{$q}.options"] = [$type === QuestionType::Multiple ? __('Mark at least one right answer.') : __('Mark exactly one right answer.')];
                }
            }
        }

        if ($errors !== []) {
            throw new DomainRuleViolation('training.modules_invalid', (string) reset($errors)[0], $errors);
        }
    }
}
