<?php

declare(strict_types=1);

use App\Domain\Access\Actions\AssignRoles;
use App\Domain\Access\Models\User;
use App\Domain\People\Actions\LinkUserAccount;
use App\Domain\People\Models\Employee;
use App\Domain\Reports\Dashboards\HrDashboard;
use App\Domain\Reports\Dashboards\ManagerDashboard;
use App\Domain\Reports\Datasets\DatasetRegistry;
use App\Domain\Reports\Models\SavedReport;
use App\Domain\Reports\Support\CustomReport;
use App\Domain\Reports\Support\CustomReportRunner;
use App\Domain\Reports\Support\DefinitionValidator;
use App\Domain\Reports\Support\ParamResolver;
use App\Domain\Reports\Support\ReportPreview;
use App\Domain\Reports\Support\ReportRegistry;
use App\Support\Actors\Actor;
use Database\Seeders\Performance\PerformanceSeeder;

/*
| Phase 9a: the role dashboards and the report screens (both loaded after the page) answer within
| their budgets at 5,000 employees: dashboards under 500 ms, a report preview under 1 s. Leave
| balances is left out on purpose: it adds up the ledger person by person, so it's for exports.
*/
it('builds dashboards and report previews fast at 5,000 employees', function (): void {
    $this->seed(PerformanceSeeder::class);

    $viewers = [];
    foreach (['hr_admin' => 'PERF-000001', 'team_lead' => 'PERF-000202'] as $role => $number) {
        $user = User::factory()->create(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()]);
        resolve(AssignRoles::class)->handle(Actor::system('test'), $user, [$role => []]);
        resolve(LinkUserAccount::class)->handle(Employee::query()->where('employee_number', $number)->sole(), $user);
        $viewers[$role] = $user->fresh();
    }

    $time = function (string $label, Closure $run, float $budget) use (&$report): void {
        $run(); // warm caches
        $started = hrtime(true);
        $run();
        $ms = (hrtime(true) - $started) / 1e6;
        $report[] = sprintf('%-34s %7.1f ms (budget %4.0f)', $label, $ms, $budget);
        expect($ms)->toBeLessThan($budget, "{$label} took {$ms} ms");
    };
    $report = [];
    $hr = $viewers['hr_admin'];

    $time('HR dashboard', fn () => resolve(HrDashboard::class)->build($hr, 'en'), 500.0);
    $time('Manager dashboard (team lead)', fn () => resolve(ManagerDashboard::class)->build($viewers['team_lead'], 'en'), 500.0);

    foreach (['people.headcount', 'people.turnover', 'people.movements', 'people.documents_expiring', 'requests.turnaround', 'training.completion'] as $key) {
        $time("report {$key}", function () use ($key, $hr): void {
            $report = resolve(ReportRegistry::class)->find($key);
            ReportPreview::build($report, $hr, resolve(ParamResolver::class)->resolve($report, ['from' => now()->subYears(2)->addDay()->toDateString(), 'to' => now()->toDateString()]), 'en');
        }, 1000.0);
    }

    $time('custom: people by department', function () use ($hr): void {
        $definition = resolve(DefinitionValidator::class)->validate(['dataset' => 'people', 'groupBy' => 'department', 'measure' => 'count', 'chart' => 'bar'], $hr);
        $draft = new SavedReport(['name' => 'p', 'dataset' => 'people', 'definition' => $definition->toArray()]);
        $report = new CustomReport($draft, resolve(DatasetRegistry::class)->find('people'), $definition, resolve(CustomReportRunner::class));
        ReportPreview::build($report, $hr, resolve(ParamResolver::class)->resolve($report, []), 'en');
    }, 1000.0);

    fwrite(STDERR, "\n".implode("\n", $report)."\n");
});
