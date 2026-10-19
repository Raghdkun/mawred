<?php

declare(strict_types=1);

namespace App\Domain\Training\Data;

use App\Domain\Access\Models\User;
use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\EmploymentType;
use App\Domain\Organization\Models\Position;
use App\Domain\Training\Models\Course;
use App\Domain\Training\Models\CourseModule;
use App\Domain\Training\Support\CourseAudience;
use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A course as trainers edit it: both languages, the audience with names, and modules with the answers. */
#[TypeScript]
final class CourseEditorData extends Data
{
    /**
     * @param  array{en: string, ar: string}  $title
     * @param  array{en: string, ar: string}  $summary
     * @param  array{en: string, ar: string}  $description
     * @param  array{id: string, name: string}|null  $owner
     * @param  array{branches: list<array{id: string, name: string}>, departments: list<array{id: string, name: string}>, positions: list<array{id: string, name: string}>, employmentTypes: list<array{id: string, name: string}>, hiredWithinDays: int|null}|null  $audience
     * @param  list<array{id: string, kind: string, title: array{en: string, ar: string}, body: array{en: string, ar: string}, url: string|null, fileName: string|null, durationMinutes: int|null, required: bool, passMark: int|null, maxAttempts: int|null, questions: list<array{id: string, type: string, prompt: array{en: string, ar: string}, points: int, options: list<array{id: string, label: array{en: string, ar: string}, correct: bool}>}>}>  $modules
     */
    public function __construct(
        public string $id,
        public string $code,
        public string $status,
        public array $title,
        public array $summary,
        public array $description,
        public string $category,
        public ?int $durationMinutes,
        public bool $requiresApproval,
        public bool $issuesCertificate,
        public ?int $validityMonths,
        public ?array $owner,
        public ?array $audience,
        public ?int $dueDays,
        public array $modules,
    ) {}

    public static function fromCourse(Course $course, string $locale): self
    {
        $pair = fn (mixed $value): array => ['en' => FieldSpec::text($value, 'en'), 'ar' => is_array($value) && is_string($value['ar'] ?? null) ? $value['ar'] : ''];
        $audience = CourseAudience::from($course->audience);
        $owner = $course->owner_user_id === null ? null : User::query()->find($course->owner_user_id);

        return new self(
            $course->ulid,
            $course->code,
            $course->status->value,
            $pair($course->getTranslations('title')),
            $pair($course->getTranslations('summary')),
            $pair($course->getTranslations('description')),
            $course->category->value,
            $course->duration_minutes,
            $course->requires_approval,
            $course->issues_certificate,
            $course->validity_months,
            $owner instanceof User ? ['id' => $owner->ulid, 'name' => $owner->name] : null,
            $audience instanceof CourseAudience ? [
                'branches' => self::named(Branch::class, $audience->branches, $locale),
                'departments' => self::named(Department::class, $audience->departments, $locale),
                'positions' => self::named(Position::class, $audience->positions, $locale, 'title'),
                'employmentTypes' => self::named(EmploymentType::class, $audience->employmentTypes, $locale),
                'hiredWithinDays' => $audience->hiredWithinDays,
            ] : null,
            $course->due_days,
            array_values($course->modules()->with('questions')->get()->map(fn (CourseModule $module): array => [
                'id' => $module->ulid,
                'kind' => $module->kind->value,
                'title' => $pair($module->getTranslations('title')),
                'body' => $pair($module->getTranslations('body')),
                'url' => $module->url,
                'fileName' => $module->file_name,
                'durationMinutes' => $module->duration_minutes,
                'required' => $module->required,
                'passMark' => $module->pass_mark,
                'maxAttempts' => $module->max_attempts,
                'questions' => array_values($module->questions->map(fn ($question): array => [
                    'id' => $question->ulid,
                    'type' => $question->type->value,
                    'prompt' => $pair($question->getTranslations('prompt')),
                    'points' => $question->points,
                    'options' => array_map(fn (array $option): array => ['id' => $option['id'], 'label' => $pair($option['label']), 'correct' => $option['correct']], $question->options),
                ])->all()),
            ])->all()),
        );
    }

    /**
     * @param  class-string<Model>  $model
     * @param  list<string>  $ulids
     * @return list<array{id: string, name: string}>
     */
    private static function named(string $model, array $ulids, string $locale, string $attribute = 'name'): array
    {
        $named = [];

        foreach ($model::query()->whereIn('ulid', $ulids)->get() as $row) {
            $named[] = ['id' => FieldSpec::string($row->getAttribute('ulid')), 'name' => FieldSpec::text(method_exists($row, 'getTranslations') ? $row->getTranslations($attribute) : null, $locale, FieldSpec::string($row->getAttribute($attribute)))];
        }

        return $named;
    }
}
