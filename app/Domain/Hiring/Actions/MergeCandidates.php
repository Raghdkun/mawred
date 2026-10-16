<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Actions;

use App\Domain\Hiring\Enums\ApplicationEventKind;
use App\Domain\Hiring\Models\ApplicationEvent;
use App\Domain\Hiring\Models\Candidate;
use App\Domain\Hiring\Models\JobApplication;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Database\ConnectionInterface;

/**
 * Folds a duplicate into the candidate we keep: applications, tags and consents move over and the
 * duplicate is left as a pointer. Two applications to the same job can't be merged; reject or
 * remove one first.
 */
final readonly class MergeCandidates
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(Actor $actor, Candidate $keep, Candidate $duplicate): Candidate
    {
        if ($keep->is($duplicate) || $duplicate->merged_into_id !== null || $keep->merged_into_id !== null) {
            throw new DomainRuleViolation('hiring.merge_invalid', __('These candidates can’t be merged.'));
        }

        $clash = JobApplication::query()->where('candidate_id', $duplicate->id)
            ->whereIn('job_requisition_id', JobApplication::query()->select('job_requisition_id')->where('candidate_id', $keep->id))
            ->exists();

        if ($clash) {
            throw new DomainRuleViolation('hiring.merge_clash', __('Both applied to the same job. Reject one of those applications, then merge.'));
        }

        return $this->db->transaction(function () use ($actor, $keep, $duplicate): Candidate {
            $moved = JobApplication::query()->where('candidate_id', $duplicate->id)->pluck('id');
            JobApplication::query()->whereKey($moved)->update(['candidate_id' => $keep->id]);

            foreach ($moved as $id) {
                ApplicationEvent::query()->create([
                    'job_application_id' => $id,
                    'kind' => ApplicationEventKind::Merged,
                    'actor_user_id' => $actor->user?->id,
                    'data' => ['from' => $duplicate->ulid],
                    'created_at' => now(),
                ]);
            }

            $keep->tags()->syncWithoutDetaching($duplicate->tags()->pluck('hiring_tags.id')->all());
            $keep->forceFill([
                'phone' => $keep->phone ?? $duplicate->phone,
                'talent_pool' => $keep->talent_pool || $duplicate->talent_pool,
                'talent_pool_consented_at' => $keep->talent_pool_consented_at ?? $duplicate->talent_pool_consented_at,
                'consented_at' => $keep->consented_at ?? $duplicate->consented_at,
                'possible_duplicate_of_id' => $keep->possible_duplicate_of_id === $duplicate->id ? null : $keep->possible_duplicate_of_id,
                'last_activity_at' => max($keep->last_activity_at, $duplicate->last_activity_at),
            ])->save();
            $duplicate->forceFill(['merged_into_id' => $keep->id, 'possible_duplicate_of_id' => null, 'talent_pool' => false])->save();
            Candidate::query()->where('possible_duplicate_of_id', $duplicate->id)->update(['possible_duplicate_of_id' => $keep->id]);

            activity('hiring')->causedBy($actor->user)->performedOn($keep)->event('merged')
                ->withProperties(['duplicate' => $duplicate->ulid, 'applications' => $moved->count()])->log('Candidates merged');

            return $keep;
        });
    }
}
