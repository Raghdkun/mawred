<?php

declare(strict_types=1);

namespace App\Domain\Reports\Support;

use App\Domain\Access\Models\User;
use App\Domain\Reports\Models\SavedReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Saved reports a person may open: their own and those shared with them or one of their roles
 * (D119). This is about the report itself; its rows are still scoped for the viewer.
 */
final class SavedReportAccess
{
    /**
     * @return Builder<SavedReport>
     */
    public static function visibleTo(User $user): Builder
    {
        $roles = $user->roles()->pluck('roles.id')->all();

        return SavedReport::query()->where(fn (Builder $q) => $q
            ->where('owner_user_id', $user->id)
            ->orWhereExists(fn (QueryBuilder $share) => $share->selectRaw('1')->from('saved_report_shares')
                ->whereColumn('saved_report_shares.saved_report_id', 'saved_reports.id')
                ->where(fn (QueryBuilder $who) => $who->where('saved_report_shares.user_id', $user->id)
                    ->when($roles !== [], fn (QueryBuilder $byRole) => $byRole->orWhereIn('saved_report_shares.role_id', $roles)))));
    }
}
