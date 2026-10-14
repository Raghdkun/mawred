<?php

declare(strict_types=1);

namespace App\Domain\Policies\Actions;

use App\Domain\Organization\Support\ReferenceValues;
use App\Domain\Policies\Data\LeaveTypeInputData;
use App\Domain\Policies\Models\LeaveType;
use App\Domain\Policies\Support\PolicyEpoch;
use App\Domain\Policies\Support\PolicyKeys;

/**
 * Leave type catalogue (D48). The code is the policy key suffix (`leave.type.<CODE>`), so it is
 * fixed once rules reference it.
 */
final readonly class SaveLeaveType
{
    public function __construct(private PolicyKeys $keys, private PolicyEpoch $epoch) {}

    public function handle(LeaveTypeInputData $input, ?LeaveType $type = null): LeaveType
    {
        $type ??= new LeaveType;
        $code = $type->exists ? $type->code : ReferenceValues::code($input->code);

        if (! $type->exists) {
            ReferenceValues::assertCodeAvailable(LeaveType::class, $code);
        }

        $type->setTranslations('name', ReferenceValues::translations($input->name));
        $type->fill([
            'code' => $code,
            'unit' => $input->unit,
            'is_paid' => $input->isPaid,
            'allows_half_days' => $input->unit === 'days' && $input->allowsHalfDays,
            'eligible_gender' => $input->eligibleGender,
            'requires_attachment' => $input->requiresAttachment,
            'sort_order' => $input->sortOrder,
            'is_active' => $input->isActive,
        ])->save();

        $this->keys->forget();
        $this->epoch->bump();

        return $type;
    }
}
