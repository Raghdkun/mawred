<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Reports\Datasets\DatasetRegistry;
use App\Domain\Reports\Models\SavedReport;
use App\Domain\Reports\Policies\SavedReportPolicy;
use App\Domain\Reports\Reports\AttendanceExceptionsReport;
use App\Domain\Reports\Reports\AuditLogReport;
use App\Domain\Reports\Reports\CertificatesExpiringReport;
use App\Domain\Reports\Reports\DocumentsExpiringReport;
use App\Domain\Reports\Reports\HeadcountReport;
use App\Domain\Reports\Reports\HiringFunnelReport;
use App\Domain\Reports\Reports\LeaveBalancesReport;
use App\Domain\Reports\Reports\LeaveTakenReport;
use App\Domain\Reports\Reports\MovementsReport;
use App\Domain\Reports\Reports\OvertimeReport;
use App\Domain\Reports\Reports\PayrollInputReport;
use App\Domain\Reports\Reports\RequestTurnaroundReport;
use App\Domain\Reports\Reports\TimeToHireReport;
use App\Domain\Reports\Reports\TrainingCompletionReport;
use App\Domain\Reports\Reports\TurnoverReport;
use App\Domain\Reports\Support\ReportRegistry;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Reports wiring (Phase 8a): the prebuilt reports in library order (D112). The registry is
 * request-scoped like the ScopeResolver its reports use.
 */
final class ReportsServiceProvider extends ServiceProvider
{
    public const array REPORTS = [
        HeadcountReport::class,
        MovementsReport::class,
        TurnoverReport::class,
        DocumentsExpiringReport::class,
        LeaveBalancesReport::class,
        LeaveTakenReport::class,
        AttendanceExceptionsReport::class,
        OvertimeReport::class,
        RequestTurnaroundReport::class,
        HiringFunnelReport::class,
        TimeToHireReport::class,
        TrainingCompletionReport::class,
        CertificatesExpiringReport::class,
        PayrollInputReport::class,
        AuditLogReport::class,
    ];

    public function boot(): void
    {
        Gate::policy(SavedReport::class, SavedReportPolicy::class);
    }

    public function register(): void
    {
        $this->app->scoped(ReportRegistry::class);
        $this->app->scoped(DatasetRegistry::class);

        $this->app->afterResolving(ReportRegistry::class, function (ReportRegistry $registry): void {
            foreach (self::REPORTS as $report) {
                $registry->register($this->app->make($report));
            }
        });
    }
}
