<?php

declare(strict_types=1);

namespace Database\Seeders\Performance;

use App\Domain\Organization\Actions\SaveBranch;
use App\Domain\Organization\Actions\SaveDepartment;
use App\Domain\Organization\Data\BranchInputData;
use App\Domain\Organization\Data\DepartmentInputData;
use App\Domain\People\Actions\HireEmployee;
use App\Domain\People\Data\HireEmployeeData;
use App\Domain\People\Models\Employee;
use App\Support\Actors\Actor;
use Database\Seeders\Demo\DemoNames;
use Database\Seeders\Production\ProductionSeeder;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Volume data for speed checks (brief §21): PERF_BRANCHES branches (default 50) and
 * PERF_EMPLOYEES people (default 5,000) spread across a 20-department tree, each branch with a
 * manager, team leads and staff. Uses the real actions, so scopes and closures are exact.
 *
 *   php artisan db:seed --class="Database\Seeders\Performance\PerformanceSeeder"
 */
final class PerformanceSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('The performance seeder never runs in production.');
        }

        $this->call(ProductionSeeder::class);

        $branchCount = max(1, config()->integer('seeding.performance.branches', 50));
        $employeeCount = max($branchCount, config()->integer('seeding.performance.employees', 5000));
        $actor = Actor::system('performance-seed');

        $departments = [];
        for ($d = 1; $d <= 20; $d++) {
            $parent = $d > 5 ? $departments[($d - 1) % 5] : null;
            $departments[] = resolve(SaveDepartment::class)->handle(new DepartmentInputData(
                sprintf('PD%02d', $d), ['en' => "Department {$d}", 'ar' => "القسم {$d}"], $parent,
            ))->ulid;
        }

        $branches = [];
        for ($b = 1; $b <= $branchCount; $b++) {
            $branches[] = resolve(SaveBranch::class)->handle(BranchInputData::from([
                'code' => sprintf('PB%03d', $b),
                'name' => ['en' => "Branch {$b}", 'ar' => "الفرع {$b}"],
                'countryCode' => 'SA', 'timezone' => 'Asia/Riyadh', 'defaultCurrency' => 'SAR', 'phoneRegion' => 'SA',
                'weekendDays' => [5, 6], 'workStartsAt' => '08:00', 'workEndsAt' => '17:00',
            ]))->ulid;
        }

        $hire = resolve(HireEmployee::class);
        $perBranch = intdiv($employeeCount, $branchCount);
        $made = 0;

        foreach ($branches as $b => $branch) {
            $size = $b === $branchCount - 1 ? $employeeCount - $made : $perBranch;
            $manager = null;
            $leads = [];

            for ($i = 0; $i < $size; $i++, $made++) {
                $name = DemoNames::arabic($made);
                $reportsTo = match (true) {
                    $i === 0 => null,
                    $i <= 5 => $manager,
                    default => $leads[$i % max(1, count($leads))] ?? $manager,
                };

                $employee = $hire->handle($actor, HireEmployeeData::from([
                    'nameEn' => $name['en'],
                    'nameAr' => $name['ar'],
                    'employeeNumber' => sprintf('PERF-%06d', $made + 1),
                    'workEmail' => sprintf('perf%06d@mawred.test', $made + 1),
                    'hireDate' => '2023-01-01',
                    'placement' => array_filter([
                        'branchId' => $branch,
                        'departmentId' => $departments[($b + $i) % count($departments)],
                        'managerId' => $reportsTo?->ulid,
                    ]),
                ]));

                if ($i === 0) {
                    $manager = $employee;
                } elseif ($i <= 5) {
                    $leads[] = $employee;
                }
            }
        }

        $this->command->info(sprintf('%d branches, %d employees.', $branchCount, Employee::query()->count()));
    }
}
