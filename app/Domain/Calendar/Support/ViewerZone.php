<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Support;

use App\Domain\Access\Models\User;
use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;

/** The timezone someone's calendar is shown in: theirs, else their branch's, else the app's. */
final class ViewerZone
{
    public static function of(User $user): string
    {
        if ($user->timezone !== null && $user->timezone !== '') {
            return $user->timezone;
        }

        $branchId = Employee::query()->where('user_id', $user->id)->value('current_branch_id');
        $branch = is_int($branchId) ? Branch::withTrashed()->find($branchId) : null;

        return $branch instanceof Branch ? $branch->timezone : FieldSpec::string(config('app.timezone'), 'UTC');
    }
}
