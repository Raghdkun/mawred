<?php

declare(strict_types=1);

namespace App\Domain\People\Support;

use App\Domain\Access\Enums\FieldAccess;
use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopeResolver;
use App\Domain\People\Enums\EmployeeStatus;
use App\Domain\People\Models\Employee;
use App\Domain\People\Queries\EmployeeDirectoryQuery;
use App\Support\Search\SearchHitData;
use App\Support\Search\SearchProvider;
use App\Support\Search\SearchText;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;

/**
 * People in the viewer's `people.view` scope, by name (either language), number, email or phone.
 * A national ID only matches exactly, through its blind index, for viewers who may read it.
 */
final readonly class EmployeeSearchProvider implements SearchProvider
{
    public function __construct(private ScopeResolver $resolver) {}

    public function type(): string
    {
        return 'employees';
    }

    public function ability(): string
    {
        return 'people.view';
    }

    public function search(Authenticatable $viewer, string $term, int $limit): array
    {
        if (! $viewer instanceof User) {
            return [];
        }

        $normalised = SearchText::normalize($term);
        $digits = (string) preg_replace('/\D+/', '', $normalised);
        $canReadNationalId = $this->resolver->fieldAccess($viewer, 'employee.national_id') instanceof FieldAccess;

        $query = resolve(EmployeeDirectoryQuery::class)
            ->withStatuses([])
            ->visibleTo($viewer)
            ->reorder()
            ->where(function (Builder $match) use ($normalised, $digits, $term, $canReadNationalId): void {
                $match->where('employees.search_text', 'like', SearchText::likePattern($normalised));

                // Local phone formats: "0501234567" should find "+966501234567".
                if (strlen($digits) >= 7) {
                    $match->orWhere('employees.search_text', 'like', SearchText::likePattern(ltrim($digits, '0')));
                }

                if ($canReadNationalId && strlen($digits) >= 6) {
                    $match->orWhere('employees.national_id_bidx', Employee::lookupHash('national_id', $term));
                }
            })
            ->orderByRaw('case when employees.employee_number = ? then 0 else 1 end', [mb_strtoupper(trim($term))])
            ->orderByRaw("case when employees.status = 'terminated' then 1 else 0 end")
            ->orderByRaw('similarity(employees.search_text, ?) desc', [$normalised])
            ->orderBy('employees.id')
            ->limit($limit);

        $locale = app()->getLocale();

        return array_values($query->get()->map(function (Employee $employee) use ($locale): SearchHitData {
            $details = array_filter([
                $employee->employee_number,
                $employee->currentPosition?->getTranslation('title', $locale),
                $employee->currentBranch?->getTranslation('name', $locale),
            ], fn (mixed $v): bool => is_string($v) && $v !== '');

            return new SearchHitData(
                type: 'employees',
                id: $employee->ulid,
                title: $employee->displayName($locale),
                subtitle: implode(' · ', $details),
                url: "/people/{$employee->ulid}",
                status: $employee->status === EmployeeStatus::Active ? null : $employee->status->value,
            );
        })->all());
    }
}
