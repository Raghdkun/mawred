<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Support;

use App\Domain\Hiring\Enums\RequisitionReason;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\People\Models\Employee;

/**
 * Reads a Job requisition request's answers (field keys from the seeded type, D84) into
 * requisition attributes. HR may reshape the form: anything missing is left empty, and the
 * branch falls back to the requester's.
 */
final class RequisitionAnswers
{
    /**
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    public static function attributes(array $answers, ?Employee $requester, string $fallbackTitle): array
    {
        $title = is_string($answers['title'] ?? null) && trim($answers['title']) !== '' ? trim($answers['title']) : $fallbackTitle;
        $branch = is_string($answers['branch'] ?? null) ? Branch::query()->where('ulid', $answers['branch'])->value('id') : null;
        $department = is_string($answers['department'] ?? null) ? Department::query()->where('ulid', $answers['department'])->value('id') : null;
        $min = self::money($answers['salary_min'] ?? null);
        $max = self::money($answers['salary_max'] ?? null);

        return [
            'title' => ['en' => $title, 'ar' => $title],
            'branch_id' => $branch ?? $requester?->current_branch_id,
            'department_id' => $department ?? ($branch === null ? $requester?->current_department_id : null),
            'headcount' => is_numeric($answers['headcount'] ?? null) ? max(1, (int) $answers['headcount']) : 1,
            'reason' => is_string($answers['reason'] ?? null) ? (RequisitionReason::tryFrom($answers['reason']) ?? RequisitionReason::New) : RequisitionReason::New,
            'hiring_manager_employee_id' => $requester?->id,
            'salary_min_minor' => $min['amount'] ?? null,
            'salary_max_minor' => $max['amount'] ?? null,
            'currency' => $min['currency'] ?? $max['currency'] ?? null,
            'target_start_on' => is_string($answers['target_start'] ?? null) ? $answers['target_start'] : null,
            'justification' => is_string($answers['justification'] ?? null) ? $answers['justification'] : null,
        ];
    }

    /**
     * @return array{amount: int, currency: string}|null
     */
    private static function money(mixed $value): ?array
    {
        if (! is_array($value) || ! is_int($value['amount_minor'] ?? null) || ! is_string($value['currency'] ?? null) || $value['currency'] === '') {
            return null;
        }

        return ['amount' => $value['amount_minor'], 'currency' => $value['currency']];
    }
}
