<?php

declare(strict_types=1);

namespace App\Domain\Reports\Support;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopeResolver;
use App\Domain\Reports\Contracts\Report;

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
