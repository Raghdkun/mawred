<?php

declare(strict_types=1);

namespace App\Domain\Training\Actions;

use App\Domain\Training\Enums\EnrollmentStatus;
use App\Domain\Training\Enums\ModuleKind;
use App\Domain\Training\Models\CourseModule;
use App\Domain\Training\Models\Enrollment;
use App\Domain\Training\Models\ModuleProgress;
use App\Domain\Training\Models\QuizAttempt;
use App\Domain\Training\Support\QuizScorer;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Database\ConnectionInterface;

/**
 * Scores an attempt at a quiz (D102). Passing marks the module done; the best attempt counts, so
 * a passed quiz takes no more attempts, and nobody gets more than the quiz allows.
 */
final readonly class SubmitQuizAttempt
{
    public function __construct(private ConnectionInterface $db, private RefreshEnrollmentProgress $refresh) {}

    /**
     * @param  array<string, list<string>>  $answers  question ULID => chosen option ids
     */
    public function handle(Enrollment $enrollment, CourseModule $module, array $answers): QuizAttempt
    {
        if ($module->course_id !== $enrollment->course_id || $module->kind !== ModuleKind::Quiz) {
            throw new DomainRuleViolation('training.module_mismatch', __('This isn’t a quiz on your course.'));
        }

        if ($enrollment->status !== EnrollmentStatus::Enrolled) {
            throw new DomainRuleViolation('training.not_enrolled', __('Enrol in this course first.'));
        }

        return $this->db->transaction(function () use ($enrollment, $module, $answers): QuizAttempt {
            Enrollment::query()->whereKey($enrollment->id)->lockForUpdate()->first();
            $attempts = QuizAttempt::query()->where('enrollment_id', $enrollment->id)->where('course_module_id', $module->id);

            if ((clone $attempts)->where('passed', true)->exists()) {
                throw new DomainRuleViolation('training.quiz_passed', __('You’ve already passed this quiz.'));
            }

            if ((clone $attempts)->count() >= $module->attemptsAllowed()) {
                throw new DomainRuleViolation('training.no_attempts', __('You’ve used every attempt at this quiz. Ask the trainer for another.'));
            }

            $questions = $module->questions()->get();
            $clean = [];

            foreach ($questions as $question) {
                $valid = array_column($question->options, 'id');
                $clean[$question->ulid] = array_values(array_intersect($answers[$question->ulid] ?? [], $valid));
            }

            $scored = [];

            foreach ($questions as $question) {
                $scored[] = [
                    'id' => $question->ulid,
                    'points' => $question->points,
                    'correct' => array_column(array_filter($question->options, fn (array $o): bool => $o['correct']), 'id'),
                ];
            }

            $score = QuizScorer::score($scored, $clean);
            $passed = $score >= $module->passMark();

            $attempt = QuizAttempt::query()->create([
                'enrollment_id' => $enrollment->id,
                'course_module_id' => $module->id,
                'answers' => $clean,
                'score' => $score,
                'passed' => $passed,
                'submitted_at' => now(),
            ]);

            if ($passed) {
                ModuleProgress::query()->insertOrIgnore(['enrollment_id' => $enrollment->id, 'course_module_id' => $module->id, 'completed_at' => now()]);
            }

            if ($enrollment->started_at === null) {
                $enrollment->forceFill(['started_at' => now()])->save();
            }

            $this->refresh->handle($enrollment);

            return $attempt;
        });
    }
}
