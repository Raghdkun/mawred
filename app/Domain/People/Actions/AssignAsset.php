<?php

declare(strict_types=1);

namespace App\Domain\People\Actions;

use App\Domain\People\Models\AssetAssignment;
use App\Domain\People\Models\Employee;
use App\Support\Errors\DomainRuleViolation;

final readonly class AssignAsset
{
    public function handle(Employee $employee, string $name, string $assetTag, string $assignedOn, ?string $category = null, ?string $serialNumber = null, ?string $dueBackOn = null, ?string $notes = null): AssetAssignment
    {
        $tag = mb_strtoupper(trim($assetTag));

        if (AssetAssignment::query()->whereRaw('upper(asset_tag) = ?', [$tag])->whereNull('returned_on')->exists()) {
            throw new DomainRuleViolation('people.asset_out', __('This asset is already with someone. Record its return first.'), ['assetTag' => [__('This asset is already with someone. Record its return first.')]]);
        }

        return AssetAssignment::query()->create([
            'employee_id' => $employee->id,
            'name' => trim($name),
            'category' => $category,
            'asset_tag' => $tag,
            'serial_number' => $serialNumber,
            'assigned_on' => $assignedOn,
            'due_back_on' => $dueBackOn,
            'condition_notes' => $notes,
        ]);
    }
}
