<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopeResolver;
use App\Domain\Hiring\Enums\MemberRole;
use App\Domain\Hiring\Models\JobRequisition;
use App\Domain\Hiring\Models\RequisitionMember;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Database\ConnectionInterface;

/**
 * Sets a requisition's hiring team. Members see every candidate for it (D82), but only if their
 * role can see hiring at all, so people without `hiring.view` are refused.
 */
final readonly class SaveRequisitionTeam
{
    public function __construct(private ConnectionInterface $db, private ScopeResolver $access) {}

    /**
     * @param  list<array{user: string, role: string}>  $members  user ULIDs and roles
     */
    public function handle(Actor $actor, JobRequisition $requisition, array $members): void
    {
        $users = User::query()->whereIn('ulid', array_column($members, 'user'))->get()->keyBy('ulid');

        foreach ($members as $i => $member) {
            $user = $users->get($member['user']);

            if (! $user instanceof User || ! $this->access->can($user, 'hiring.view')) {
                $message = __('This person’s role can’t see hiring. Give them a hiring role first.');

                throw new DomainRuleViolation('hiring.member_without_access', $message, ["members.{$i}.user" => [$message]]);
            }
        }

        $this->db->transaction(function () use ($actor, $requisition, $members, $users): void {
            $keep = [];

            foreach ($members as $member) {
                /** @var User $user */
                $user = $users->get($member['user']);
                $keep[] = $user->id;
                RequisitionMember::query()->updateOrCreate(
                    ['job_requisition_id' => $requisition->id, 'user_id' => $user->id],
                    ['role' => MemberRole::from($member['role'])],
                );
            }

            $requisition->members()->whereNotIn('user_id', $keep)->delete();

            activity('hiring')->causedBy($actor->user)->performedOn($requisition)->event('team_changed')
                ->withProperties(['members' => array_map(fn (array $m): array => ['user' => $m['user'], 'role' => $m['role']], $members)])
                ->log('Hiring team changed');
        });
    }
}
