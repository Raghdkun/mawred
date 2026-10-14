<?php

declare(strict_types=1);

namespace App\Domain\Policies\Actions;

use App\Domain\Policies\Models\LeaveType;
use App\Domain\Policies\Support\PolicyEpoch;
use App\Domain\Policies\Support\PolicyKeys;

/**
 * Archives a leave type. Its `leave.type.<CODE>` values stay as history; the key leaves the
 * registry, so nothing resolves it from now on and the code cannot be reused.
 */
final readonly class DeleteLeaveType
{
    public function __construct(private PolicyKeys $keys, private PolicyEpoch $epoch) {}

    public function handle(LeaveType $type): void
    {
        $type->delete();

        $this->keys->forget();
        $this->epoch->bump();
    }
}
