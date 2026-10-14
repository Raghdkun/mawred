<?php

declare(strict_types=1);

namespace App\Domain\People\Actions;

use App\Domain\Organization\Models\Branch;
use App\Domain\People\Data\EmployeeProfileInputData;
use App\Domain\People\Data\HireEmployeeData;
use App\Domain\People\Data\PlacementInputData;
use App\Domain\People\Data\SensitiveDetailsInputData;
use App\Domain\People\Enums\ImportRowStatus;
use App\Domain\People\Enums\ImportStatus;
use App\Domain\People\Models\Employee;
use App\Domain\People\Models\Import;
use App\Domain\People\Models\ImportRow;
use App\Domain\People\Support\Imports\ImportErrorReport;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use App\Support\Time\LocalClock;
use Illuminate\Validation\ValidationException;

/**
 * Applies a validated import: new people through HireEmployee, existing ones through the profile,
 * placement, sensitive-detail and custom-field actions, so every rule and audit entry applies.
 * Rows go in level order (managers first), 500 at a time; a failing row is recorded and the rest
 * carry on.
 */
final readonly class CommitImport
{
    public function __construct(
        private HireEmployee $hire,
        private UpdateEmployeeProfile $profile,
        private ChangePlacement $placement,
        private UpdateSensitiveDetails $sensitive,
        private SetCustomFieldValues $custom,
        private LocalClock $clock,
    ) {}

    public function handle(Import $import, Actor $actor): Import
    {
        if ($import->status !== ImportStatus::Committing) {
            $import->forceFill(['status' => ImportStatus::Committing])->save();
        }

        $levels = ImportRow::query()->where('import_id', $import->id)->where('status', ImportRowStatus::Valid)->distinct()->orderBy('level')->pluck('level');

        foreach ($levels as $level) {
            ImportRow::query()
                ->where('import_id', $import->id)
                ->where('status', ImportRowStatus::Valid)
                ->where('level', $level)
                ->chunkById(ValidateImport::CHUNK, function ($rows) use ($import, $actor): void {
                    $created = $updated = $failed = 0;

                    foreach ($rows as $row) {
                        /** @var ImportRow $row */
                        try {
                            $employee = $this->apply($import, $row, $actor);
                            $row->forceFill(['status' => ImportRowStatus::Done, 'employee_id' => $employee->id, 'action' => $row->normalized['action'] ?? null])->save();
                            ($row->normalized['action'] ?? null) === 'create' ? $created++ : $updated++;
                        } catch (DomainRuleViolation $e) {
                            $row->forceFill(['status' => ImportRowStatus::Failed, 'errors' => $e->details === [] ? ['row' => [$e->getMessage()]] : $e->details])->save();
                            $failed++;
                        } catch (ValidationException $e) {
                            $row->forceFill(['status' => ImportRowStatus::Failed, 'errors' => $e->errors()])->save();
                            $failed++;
                        }
                    }

                    $import->increment('processed_rows', $created + $updated + $failed, [
                        'created_rows' => $import->created_rows + $created,
                        'updated_rows' => $import->updated_rows + $updated,
                        'failed_rows' => $import->failed_rows + $failed,
                    ]);
                    $import->refresh();
                });
        }

        $import->forceFill(['status' => ImportStatus::Completed, 'committed_at' => now()])->save();

        if ($import->failed_rows > 0) {
            ImportErrorReport::write($import);
        }

        activity('import')->causedBy($actor->user)->performedOn($import)->event('committed')
            ->withProperties(['created' => $import->created_rows, 'updated' => $import->updated_rows, 'failed' => $import->failed_rows])
            ->log('Import committed');

        return $import;
    }

    private function apply(Import $import, ImportRow $row, Actor $actor): Employee
    {
        /** @var array<string, mixed> $data */
        $data = $row->normalized ?? [];
        $managerUlid = $this->managerUlid($import, $data);
        $placement = is_array($data['placement'] ?? null) ? $data['placement'] : [];

        if (($data['action'] ?? null) === 'create') {
            $employee = $this->hire->handle($actor, HireEmployeeData::validateAndCreate(array_filter([
                'nameEn' => $data['name_en'] ?? null,
                'nameAr' => $data['name_ar'] ?? null,
                'preferredName' => $data['preferred_name'] ?? null,
                'employeeNumber' => $data['employee_number'] ?? null,
                'workEmail' => $data['work_email'] ?? null,
                'workPhone' => $data['work_phone'] ?? null,
                'gender' => $data['gender'] ?? null,
                'nationality' => $data['nationality'] ?? null,
                'hireDate' => $data['hire_date'] ?? null,
                'probationEndsOn' => $data['probation_ends_on'] ?? null,
                'placement' => array_filter([
                    'branchId' => $placement['branch'] ?? null,
                    'departmentId' => $placement['department'] ?? null,
                    'positionId' => $placement['position'] ?? null,
                    'employmentTypeId' => $placement['employment_type'] ?? null,
                    'gradeId' => $placement['grade'] ?? null,
                    'managerId' => $managerUlid,
                ], fn (mixed $v): bool => $v !== null),
            ], fn (mixed $v): bool => $v !== null)));
        } else {
            $employee = Employee::query()->where('ulid', $data['employee'] ?? '')->firstOrFail();
            $this->update($actor, $employee, $data, $managerUlid);
        }

        $this->extras($actor, $employee, $data);

        return $employee;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function update(Actor $actor, Employee $employee, array $data, ?string $managerUlid): void
    {
        $profileFields = ['name_en', 'name_ar', 'preferred_name', 'work_email', 'work_phone', 'gender', 'nationality', 'hire_date', 'probation_ends_on'];

        if (array_intersect_key($data, array_flip($profileFields)) !== []) {
            $this->profile->handle($employee, EmployeeProfileInputData::validateAndCreate([
                'nameEn' => $data['name_en'] ?? $employee->name_en,
                'nameAr' => $data['name_ar'] ?? $employee->name_ar,
                'preferredName' => $data['preferred_name'] ?? $employee->preferred_name,
                'workEmail' => $data['work_email'] ?? $employee->work_email,
                'workPhone' => $data['work_phone'] ?? $employee->work_phone_e164,
                'gender' => $data['gender'] ?? $employee->gender,
                'nationality' => $data['nationality'] ?? $employee->nationality,
                'maritalStatus' => $employee->marital_status,
                'hireDate' => $data['hire_date'] ?? $employee->hire_date->toDateString(),
                'probationEndsOn' => $data['probation_ends_on'] ?? $employee->probation_ends_on?->toDateString(),
            ]));
        }

        if (! isset($data['placement']) && $managerUlid === null) {
            return;
        }

        $employee->loadMissing(['currentBranch:id,ulid,timezone', 'currentDepartment:id,ulid', 'currentPosition:id,ulid', 'currentManager:id,ulid', 'currentEmploymentType:id,ulid', 'currentGrade:id,ulid']);

        $current = [
            'branchId' => $employee->currentBranch?->ulid,
            'departmentId' => $employee->currentDepartment?->ulid,
            'positionId' => $employee->currentPosition?->ulid,
            'managerId' => $employee->currentManager?->ulid,
            'employmentTypeId' => $employee->currentEmploymentType?->ulid,
            'gradeId' => $employee->currentGrade?->ulid,
        ];
        $placement = is_array($data['placement'] ?? null) ? $data['placement'] : [];
        $wanted = array_merge($current, array_filter([
            'branchId' => $placement['branch'] ?? null,
            'departmentId' => $placement['department'] ?? null,
            'positionId' => $placement['position'] ?? null,
            'managerId' => $managerUlid,
            'employmentTypeId' => $placement['employment_type'] ?? null,
            'gradeId' => $placement['grade'] ?? null,
        ], fn (mixed $v): bool => $v !== null));

        if ($wanted === $current || $wanted['branchId'] === null) {
            return;
        }

        $timezone = Branch::query()->where('ulid', $wanted['branchId'])->value('timezone');
        $today = $this->clock->today(is_string($timezone) ? $timezone : 'UTC');
        $from = max($today, $employee->hire_date->toDateString());

        $this->placement->handle($actor, $employee, PlacementInputData::from($wanted), $from, 'import');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function extras(Actor $actor, Employee $employee, array $data): void
    {
        $sensitive = array_filter([
            'nationalId' => isset($data['national_id']) ? ['number' => $data['national_id']] : null,
            'dateOfBirth' => $data['date_of_birth'] ?? null,
        ], fn (mixed $v): bool => $v !== null);

        if ($sensitive !== []) {
            $this->sensitive->handle($actor, $employee, SensitiveDetailsInputData::from($sensitive));
        }

        if (isset($data['custom']) && is_array($data['custom']) && $data['custom'] !== []) {
            /** @var array<string, mixed> $custom */
            $custom = $data['custom'];
            $this->custom->handle($actor, $employee, $custom);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function managerUlid(Import $import, array $data): ?string
    {
        $manager = $data['manager'] ?? null;

        if (! is_array($manager)) {
            return null;
        }

        if (isset($manager['employee']) && is_string($manager['employee'])) {
            return $manager['employee'];
        }

        if (isset($manager['row'])) {
            $managerRow = ImportRow::query()->where('import_id', $import->id)->where('row_number', $manager['row'])->first(['employee_id', 'status']);

            if ($managerRow?->employee_id === null) {
                throw new DomainRuleViolation('people.import_manager_failed', __('This manager\'s row has errors. Fix it first.'), ['manager' => [__('This manager\'s row has errors. Fix it first.')]]);
            }

            $ulid = Employee::query()->whereKey($managerRow->employee_id)->value('ulid');

            return is_string($ulid) ? $ulid : null;
        }

        return null;
    }
}
