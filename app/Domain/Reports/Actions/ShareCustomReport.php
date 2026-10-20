<?php

declare(strict_types=1);

namespace App\Domain\Reports\Actions;

use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\User;
use App\Domain\People\Models\Employee;
use App\Domain\Reports\Models\SavedReport;
use App\Domain\Reports\Models\SavedReportShare;
use App\Support\Actors\Actor;
use Illuminate\Database\ConnectionInterface;

/**
 * Sets who a saved report is shared with (D119): people (by their employee record) and roles.
 * Replaces the previous list. Sharing never widens what anyone sees: each viewer's rows stay in
 * their own scope.
 */
final readonly class ShareCustomReport
{
    public function __construct(private ConnectionInterface $db) {}

    /**
     * @param  list<string>  $employees  employee ULIDs
     * @param  list<string>  $roles  role names
     * @return array{people: int, roles: int}
     */
    public function handle(Actor $actor, SavedReport $report, array $employees, array $roles): array
    {
        $userIds = Employee::query()->whereIn('ulid', $employees)->whereNotNull('user_id')->pluck('user_id')
            ->filter(fn (mixed $id): bool => is_int($id) && $id !== $report->owner_user_id)->unique()->values()->all();
        $userIds = User::query()->whereKey($userIds)->pluck('id')->all();
        $roleIds = Role::query()->whereIn('name', $roles)->pluck('id')->all();

        $this->db->transaction(function () use ($report, $userIds, $roleIds, $actor): void {
            $report->shares()->delete();

            foreach ($userIds as $id) {
                SavedReportShare::query()->create(['saved_report_id' => $report->id, 'user_id' => $id, 'created_at' => now()]);
            }

            foreach ($roleIds as $id) {
                SavedReportShare::query()->create(['saved_report_id' => $report->id, 'role_id' => $id, 'created_at' => now()]);
            }

            activity('reports')->causedBy($actor->user)->performedOn($report)->event('shared')
                ->withProperties(['people' => count($userIds), 'roles' => count($roleIds)])
                ->log('Saved report shared');
        });

        return ['people' => count($userIds), 'roles' => count($roleIds)];
    }
}
