<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Queries;

use App\Domain\Access\Queries\ScopedQuery;
use App\Domain\Access\Support\ScopeResolver;
use App\Domain\Access\Support\SubjectBinding;
use App\Domain\Hiring\Models\Candidate;
use App\Domain\Hiring\Support\HiringScope;
use App\Support\Search\SearchText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Candidates and the talent pool (`hiring.view`): people the viewer can see through an
 * application on a job they can see. Searches by name or exact email; filters by tag, the
 * talent pool and possible duplicates. Merged and anonymised candidates never show.
 *
 * @extends ScopedQuery<Candidate>
 */
final class CandidatesQuery extends ScopedQuery
{
    private string $search = '';

    private ?int $tagId = null;

    private bool $poolOnly = false;

    private bool $duplicatesOnly = false;

    public function __construct(ScopeResolver $resolver, private readonly HiringScope $scope)
    {
        parent::__construct($resolver);
    }

    public function search(string $search): self
    {
        $this->search = trim($search);

        return $this;
    }

    public function taggedWith(?int $tagId): self
    {
        $this->tagId = $tagId;

        return $this;
    }

    public function poolOnly(bool $poolOnly = true): self
    {
        $this->poolOnly = $poolOnly;

        return $this;
    }

    public function duplicatesOnly(bool $duplicatesOnly = true): self
    {
        $this->duplicatesOnly = $duplicatesOnly;

        return $this;
    }

    protected function permission(): string
    {
        return 'hiring.view';
    }

    protected function subject(): SubjectBinding
    {
        return $this->scope->candidates();
    }

    /**
     * @return Builder<Candidate>
     */
    protected function baseQuery(): Builder
    {
        $email = str_contains($this->search, '@') ? Candidate::emailHash($this->search) : null;
        $term = SearchText::from([$this->search]);

        return Candidate::query()
            ->whereNull('candidates.merged_into_id')
            ->whereNull('candidates.anonymised_at')
            ->when($this->search !== '', fn (Builder $q) => $email !== null
                ? $q->where('candidates.email_bidx', $email)
                : $q->where('candidates.search_text', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->when($this->tagId !== null, fn (Builder $q) => $q->whereIn('candidates.id', fn (QueryBuilder $sub) => $sub->select('candidate_id')->from('candidate_tag')->where('hiring_tag_id', $this->tagId)))
            ->when($this->poolOnly, fn (Builder $q) => $q->where('candidates.talent_pool', true))
            ->when($this->duplicatesOnly, fn (Builder $q) => $q->whereNotNull('candidates.possible_duplicate_of_id'))
            ->latest('candidates.last_activity_at')
            ->orderByDesc('candidates.id');
    }
}
