<?php

declare(strict_types=1);

namespace App\Domain\People\Support\Imports;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopeResolver;
use App\Domain\Access\Support\SubjectBinding;
use App\Domain\People\Enums\EmployeeStatus;
use App\Domain\People\Enums\Gender;
use App\Domain\People\Models\Employee;
use App\Domain\People\Support\CustomFieldValues;
use App\Domain\People\Support\EmployeeNumbers;
use App\Domain\People\Support\FieldGuard;
use App\Support\Actors\Actor;
use App\Support\Phone\Phones;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Validates and normalises employee import rows, a chunk at a time (existing employees for the
 * chunk are matched in two queries). Blank cells mean "leave unchanged" for updates (D39:
 * match by employee number, else by work email).
 */
final class EmployeeRowValidator
{
    /** @var array<string, int> identifier (number/email) => row number, across the whole file */
    private array $seen = [];

    public function __construct(
        private readonly Actor $actor,
        private readonly ImportLookups $lookups,
        private readonly FieldGuard $guard,
        private readonly ScopeResolver $resolver,
    ) {}

    /**
     * @param  array<int, list<string|null>>  $rows  row number => cells
     * @param  array<array-key, string>  $mapping  column index => field
     * @return array<int, array{normalized: array<string, mixed>|null, errors: array<string, list<string>>}>
     */
    public function chunk(array $rows, array $mapping): array
    {
        $values = [];
        $numbers = [];
        $emails = [];

        foreach ($rows as $number => $cells) {
            $row = [];

            foreach ($mapping as $column => $field) {
                $cell = $cells[(int) $column] ?? null;

                if ($cell !== null && trim($cell) !== '') {
                    $row[$field] = trim($cell);
                }
            }

            $values[$number] = $row;

            if (isset($row['employee_number'])) {
                $numbers[] = EmployeeNumbers::normalise($row['employee_number']);
            }

            if (isset($row['work_email'])) {
                $emails[] = mb_strtolower($row['work_email']);
            }

            if (isset($row['manager'])) {
                str_contains($row['manager'], '@') ? $emails[] = mb_strtolower($row['manager']) : $numbers[] = EmployeeNumbers::normalise($row['manager']);
            }
        }

        $byNumber = Employee::query()->whereIn('employee_number', array_values(array_unique($numbers)))->get()->keyBy('employee_number');
        $byEmail = Employee::query()->whereIn('work_email', array_values(array_unique($emails)))->get()->keyBy(fn (Employee $e): string => mb_strtolower((string) $e->work_email));

        $results = [];

        foreach ($values as $number => $row) {
            $results[$number] = $this->row($number, $row, $byNumber->all(), $byEmail->all());
        }

        return $results;
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<array-key, Employee>  $byNumber
     * @param  array<array-key, Employee>  $byEmail
     * @return array{normalized: array<string, mixed>|null, errors: array<string, list<string>>}
     */
    private function row(int $rowNumber, array $row, array $byNumber, array $byEmail): array
    {
        $errors = [];
        $error = function (string $field, string $message) use (&$errors): void {
            $errors[$field][] = $message;
        };

        if ($row === []) {
            return ['normalized' => null, 'errors' => ['row' => [__('This row is empty.')]]];
        }

        $number = isset($row['employee_number']) ? EmployeeNumbers::normalise($row['employee_number']) : null;
        $email = isset($row['work_email']) ? mb_strtolower($row['work_email']) : null;

        if ($number !== null && preg_match('/^[A-Z0-9][A-Z0-9_-]{0,31}$/', $number) !== 1) {
            $error('employee_number', __('Use letters, digits, - or _ (up to 32).'));
        }

        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $error('work_email', __('Enter a valid email address.'));
        }

        foreach (['number' => $number, 'email' => $email] as $kind => $identifier) {
            if ($identifier === null) {
                continue;
            }

            if (isset($this->seen["{$kind}:{$identifier}"])) {
                $error($kind === 'number' ? 'employee_number' : 'work_email', __('Already used on row :row.', ['row' => $this->seen["{$kind}:{$identifier}"]]));
            } else {
                $this->seen["{$kind}:{$identifier}"] = $rowNumber;
            }
        }

        $existing = ($number !== null ? ($byNumber[$number] ?? null) : null) ?? ($email !== null ? ($byEmail[$email] ?? null) : null);

        if ($existing instanceof Employee && $email !== null && isset($byEmail[$email]) && ! $byEmail[$email]->is($existing)) {
            $error('work_email', __('Another employee already uses this work email.'));
        }

        if ($existing instanceof Employee && $number !== null && $existing->employee_number !== $number) {
            $error('employee_number', __('This work email belongs to employee :number.', ['number' => $existing->employee_number]));
        }

        if ($existing instanceof Employee && ($this->actor->user instanceof User) && ! $this->resolver->allows($this->actor->user, 'people.update', $existing, SubjectBinding::employee('employees.id'))) {
            $error('employee_number', __('You cannot update this employee.'));
        }

        $normalized = [
            'action' => $existing instanceof Employee ? 'update' : 'create',
            'employee' => $existing?->ulid,
            'key_number' => $number,
            'key_email' => $email,
        ];

        if (! $existing instanceof Employee) {
            foreach (EmployeeImportFields::REQUIRED_FOR_CREATE as $field) {
                if (! isset($row[$field])) {
                    $error($field, __('Required for new employees.'));
                }
            }
        }

        foreach (['employee_number' => $number, 'work_email' => $email, 'name_en' => $row['name_en'] ?? null, 'name_ar' => $row['name_ar'] ?? null, 'preferred_name' => $row['preferred_name'] ?? null] as $field => $value) {
            if ($value !== null) {
                $normalized[$field] = $value;
            }
        }

        foreach (['name_en' => 160, 'name_ar' => 160, 'preferred_name' => 80] as $field => $max) {
            if (isset($row[$field]) && mb_strlen($row[$field]) > $max) {
                $error($field, __('Keep this under :max characters.', ['max' => $max]));
            }
        }

        foreach (['hire_date', 'probation_ends_on', 'date_of_birth'] as $field) {
            if (isset($row[$field])) {
                $date = self::date($row[$field]);
                $date === null ? $error($field, __('Use a date like 2025-03-01 or 01/03/2025.')) : $normalized[$field] = $date;
            }
        }

        if (isset($row['gender'])) {
            $gender = $this->gender($row['gender']);
            $gender === null ? $error('gender', __('Use male or female.')) : $normalized['gender'] = $gender;
        }

        if (isset($row['nationality'])) {
            preg_match('/^[A-Za-z]{2}$/', $row['nationality']) === 1
                ? $normalized['nationality'] = mb_strtoupper($row['nationality'])
                : $error('nationality', __('Use the two-letter country code, e.g. SA.'));
        }

        $placement = [];

        foreach (['branch', 'department', 'position', 'employment_type', 'grade'] as $kind) {
            if (isset($row[$kind])) {
                $ulid = $this->lookups->ref($kind, $row[$kind]);
                $ulid === null ? $error($kind, __('No active match for “:value”.', ['value' => $row[$kind]])) : $placement[$kind] = $ulid;
            }
        }

        if ($placement !== []) {
            $normalized['placement'] = $placement;
        }

        $branchUlid = $placement['branch'] ?? ($existing?->current_branch_id !== null ? ($this->lookups->branchUlids[$existing->current_branch_id] ?? null) : null);
        $region = $branchUlid === null ? null : ($this->lookups->branchRegions[$branchUlid] ?? null);

        if (isset($row['work_phone'])) {
            $phone = Phones::toE164($row['work_phone'], $region);
            is_string($phone) ? $normalized['work_phone'] = $phone : $error('work_phone', __('Enter a valid phone number.'));
        }

        if (isset($row['manager'])) {
            $managerKey = str_contains($row['manager'], '@') ? 'email:'.mb_strtolower($row['manager']) : 'number:'.EmployeeNumbers::normalise($row['manager']);
            $manager = str_contains($row['manager'], '@') ? ($byEmail[mb_strtolower($row['manager'])] ?? null) : ($byNumber[EmployeeNumbers::normalise($row['manager'])] ?? null);

            if ($manager instanceof Employee) {
                if ($manager->status === EmployeeStatus::Terminated) {
                    $error('manager', __('Choose a manager who still works here.'));
                } elseif ($existing instanceof Employee && $manager->is($existing)) {
                    $error('manager', __('Someone cannot report to themselves or to a person in their own team.'));
                } else {
                    $normalized['manager'] = ['employee' => $manager->ulid];
                }
            } else {
                // Resolved against the rest of the file once every row has been read.
                $normalized['manager'] = ['key' => $managerKey];
            }
        }

        foreach (['national_id' => 'employee.national_id', 'date_of_birth' => 'employee.date_of_birth'] as $field => $fieldKey) {
            if (isset($row[$field]) && ! $this->guard->canWrite($this->actor, $fieldKey)) {
                $error($field, __('You do not have permission to change this field.'));
            }
        }

        if (isset($row['national_id'])) {
            $normalized['national_id'] = $row['national_id'];
        }

        $custom = [];

        foreach ($row as $field => $value) {
            if (! str_starts_with($field, 'custom.')) {
                continue;
            }

            $definition = $this->lookups->customFields->get(substr($field, 7));

            if ($definition === null) {
                continue;
            }

            if ($definition->is_sensitive && ! $this->guard->canWrite($this->actor, $definition->fieldKey())) {
                $error($field, __('You do not have permission to change this field.'));

                continue;
            }

            $input = $definition->type->hasOptions() && $definition->type->value === 'multiselect' ? array_map(trim(...), explode(',', $value)) : $value;
            [$clean, $message] = CustomFieldValues::normalise($definition, $input, $region);
            $message === null ? $custom[$definition->key] = $clean : $error($field, $message);
        }

        if ($custom !== []) {
            $normalized['custom'] = $custom;
        }

        return ['normalized' => $errors === [] ? $normalized : null, 'errors' => $errors];
    }

    public static function date(string $value): ?string
    {
        $value = strtr(trim($value), ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);

        foreach (['!Y-m-d', '!d/m/Y', '!j/n/Y', '!d-m-Y', '!j-n-Y', '!d.m.Y', '!Y/m/d'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat($format, $value);
            } catch (Throwable) {
                continue;
            }

            if ($date instanceof CarbonImmutable && $date->format(ltrim($format, '!')) === $value && $date->year >= 1900 && $date->year <= 2200) {
                return $date->toDateString();
            }
        }

        return null;
    }

    private function gender(string $value): ?string
    {
        return match (mb_strtolower(trim($value))) {
            'male', 'm', 'ذكر' => Gender::Male->value,
            'female', 'f', 'أنثى', 'انثى' => Gender::Female->value,
            default => null,
        };
    }
}
