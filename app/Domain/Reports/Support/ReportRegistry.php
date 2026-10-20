<?php

declare(strict_types=1);

namespace App\Domain\Reports\Support;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopeResolver;
use App\Domain\Reports\Contracts\Report;
use App\Domain\Reports\Datasets\BaseDataset;
use App\Domain\Reports\Datasets\DatasetRegistry;
use App\Domain\Reports\Models\SavedReport;
use App\Support\Errors\DomainRuleViolation;

/**
 * The prebuilt reports (D112), registered by the Reports provider. Someone can open a report when
 * they hold `reports.view` and the module permission it names; exporting also needs
 * `reports.export`.
 */
final class ReportRegistry
{
    /** @var array<string, Report> */
    private array $reports = [];

    public function __construct(private readonly ScopeResolver $resolver) {}

    public function register(Report $report): void
    {
        $this->reports[$report->key()] = $report;
    }

    public function find(string $key): ?Report
    {
        return $this->reports[$key] ?? null;
    }

    /**
     * A prebuilt report, or a saved custom report this person may open (theirs or shared with
     * them, D119), built for them: their field permissions decide its columns. Null when it
     * doesn't exist or isn't theirs to see.
     */
    public function resolve(string $key, User $user): ?Report
    {
        if (! str_starts_with($key, SavedReport::KEY_PREFIX)) {
            return $this->find($key);
        }

        $saved = SavedReportAccess::visibleTo($user)->where('ulid', substr($key, strlen(SavedReport::KEY_PREFIX)))->first();

        return $saved instanceof SavedReport ? $this->custom($saved, $user) : null;
    }

    /** A saved report built for one viewer, or null when they can't run it (dataset or fields). */
    public function custom(SavedReport $saved, User $user): ?CustomReport
    {
        $dataset = resolve(DatasetRegistry::class)->find($saved->dataset);

        if (! $dataset instanceof BaseDataset || ! $dataset->canUse($user)) {
            return null;
        }

        try {
            $definition = resolve(DefinitionValidator::class)->validate($saved->definition, $user);
        } catch (DomainRuleViolation) {
            return null;
        }

        return new CustomReport($saved, $dataset, $definition, resolve(CustomReportRunner::class));
    }

    /**
     * @return list<Report>
     */
    public function all(): array
    {
        return array_values($this->reports);
    }

    /**
     * @return list<Report>
     */
    public function for(User $user): array
    {
        return array_values(array_filter($this->reports, fn (Report $report): bool => $this->canView($user, $report)));
    }

    public function canView(User $user, Report $report): bool
    {
        return $this->resolver->can($user, 'reports.view') && $this->resolver->can($user, $report->permission());
    }

    public function canExport(User $user, Report $report): bool
    {
        return $this->canView($user, $report) && $this->resolver->can($user, 'reports.export');
    }
}
