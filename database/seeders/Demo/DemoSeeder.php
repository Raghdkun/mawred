<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Domain\Access\Actions\AssignRoles;
use App\Domain\Access\Models\User;
use App\Domain\Organization\Actions\SaveBranch;
use App\Domain\Organization\Actions\SaveCatalogEntry;
use App\Domain\Organization\Actions\SaveDepartment;
use App\Domain\Organization\Actions\SavePosition;
use App\Domain\Organization\Data\BranchInputData;
use App\Domain\Organization\Data\CatalogEntryInputData;
use App\Domain\Organization\Data\DepartmentInputData;
use App\Domain\Organization\Data\PositionInputData;
use App\Domain\Organization\Enums\CatalogKind;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\EmploymentType;
use App\Domain\People\Actions\AddSecondaryAssignment;
use App\Domain\People\Actions\HireEmployee;
use App\Domain\People\Actions\LinkUserAccount;
use App\Domain\People\Actions\RecordSalaryChange;
use App\Domain\People\Actions\SaveCustomFieldDefinition;
use App\Domain\People\Actions\SaveEmployeeDocument;
use App\Domain\People\Actions\SetCustomFieldValues;
use App\Domain\People\Actions\TerminateEmployee;
use App\Domain\People\Actions\UpdateSensitiveDetails;
use App\Domain\People\Data\CustomFieldDefinitionInputData;
use App\Domain\People\Data\EmployeeDocumentInputData;
use App\Domain\People\Data\HireEmployeeData;
use App\Domain\People\Data\PlacementInputData;
use App\Domain\People\Data\SensitiveDetailsInputData;
use App\Domain\People\Models\DocumentType;
use App\Domain\People\Models\Employee;
use App\Domain\Policies\Actions\AssignPolicySet;
use App\Domain\Policies\Actions\InstallHolidayPreset;
use App\Domain\Policies\Actions\SaveHolidayCalendar;
use App\Domain\Policies\Actions\SaveHolidayRule;
use App\Domain\Policies\Actions\SavePolicySet;
use App\Domain\Policies\Actions\SeedPolicyDefaults;
use App\Domain\Policies\Actions\SetPolicyValues;
use App\Domain\Policies\Data\HolidayCalendarInputData;
use App\Domain\Policies\Data\HolidayRuleInputData;
use App\Domain\Policies\Enums\PolicyLevel;
use App\Domain\Policies\Support\HolidayPresets;
use App\Support\Actors\Actor;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Database\Seeders\Production\ProductionSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Demo company (brief §21): 3 branches with different weekends and time zones, 8 departments,
 * ~120 people with Arabic and English names in a real reporting tree, sample documents, salaries,
 * custom fields, a future hire and a leaver, and one sign-in per role.
 *
 *   DEMO_PASSWORD=… php artisan db:seed --class="Database\Seeders\Demo\DemoSeeder"
 *
 * Demo sign-ins share DEMO_PASSWORD (local only). Everything goes through the real actions.
 */
final class DemoSeeder extends Seeder
{
    /** role => sign-in email */
    private const array ACCOUNTS = [
        'hr_admin' => 'hr.admin@mawred.test',
        'hr_officer' => 'hr.officer@mawred.test',
        'branch_manager' => 'branch.manager@mawred.test',
        'department_manager' => 'dept.manager@mawred.test',
        'team_lead' => 'team.lead@mawred.test',
        'employee' => 'employee@mawred.test',
    ];

    private Actor $actor;

    /** @var array<string, string> code => ulid */
    private array $ids = [];

    private int $nameIndex = 0;

    private int $phone = 0;

    /** @var array<string, Employee> role => employee who gets that sign-in */
    private array $accounts = [];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('The demo seeder never runs in production.');
        }

        $password = config('seeding.demo_password');

        if (! is_string($password) || strlen($password) < 12) {
            throw new RuntimeException('Set DEMO_PASSWORD (12+ characters) in .env before seeding demo data.');
        }

        $this->call(ProductionSeeder::class);

        if (Branch::query()->where('code', 'RUH-HQ')->exists()) {
            $this->command->info('Demo data is already there.');

            return;
        }

        $this->actor = Actor::system('demo-seed');

        $this->catalogs();
        $this->branches();
        $this->departments();
        $this->positions();
        $this->customFields();
        $this->people();
        $this->policies();
        $this->accounts($password);

        $this->command->info(sprintf('Demo company ready: %d people. Sign in as %s with DEMO_PASSWORD.', Employee::query()->count(), implode(', ', self::ACCOUNTS)));
    }

    private function catalogs(): void
    {
        $save = resolve(SaveCatalogEntry::class);

        foreach ([['CC-CORP', 'Corporate', 'الإدارة العامة'], ['CC-OPS', 'Operations', 'العمليات'], ['CC-SALES', 'Sales', 'المبيعات']] as [$code, $en, $ar]) {
            $this->ids[$code] = $save->handle(CatalogKind::CostCenter, new CatalogEntryInputData($code, ['en' => $en, 'ar' => $ar]))->ulid;
        }

        for ($rank = 1; $rank <= 8; $rank++) {
            $this->ids["G{$rank}"] = $save->handle(CatalogKind::Grade, new CatalogEntryInputData("G{$rank}", ['en' => "Grade {$rank}", 'ar' => "الدرجة {$rank}"], $rank))->ulid;
        }

        foreach (EmploymentType::query()->get() as $type) {
            $this->ids[$type->code] = $type->ulid;
        }
    }

    private function branches(): void
    {
        $branches = [
            ['RUH-HQ', ['en' => 'Riyadh HQ', 'ar' => 'المقر الرئيسي - الرياض'], 'SA', 'Asia/Riyadh', 'SAR', [5, 6], '08:00', '17:00', ['lat' => 24.7136, 'lng' => 46.6753, 'radiusM' => 150], 'Riyadh', 'CC-CORP'],
            ['DXB', ['en' => 'Dubai Marina', 'ar' => 'دبي مارينا'], 'AE', 'Asia/Dubai', 'AED', [6, 7], '09:00', '18:00', ['lat' => 25.0805, 'lng' => 55.1403, 'radiusM' => 120], 'Dubai', 'CC-SALES'],
            ['NYC', ['en' => 'New York Office', 'ar' => 'مكتب نيويورك'], 'US', 'America/New_York', 'USD', [6, 7], '09:00', '17:00', null, 'New York', 'CC-CORP'],
        ];

        foreach ($branches as [$code, $name, $country, $timezone, $currency, $weekend, $start, $end, $geofence, $city, $costCenter]) {
            $this->ids[$code] = resolve(SaveBranch::class)->handle(BranchInputData::from([
                'code' => $code, 'name' => $name, 'countryCode' => $country, 'timezone' => $timezone,
                'defaultCurrency' => $currency, 'phoneRegion' => $country, 'weekendDays' => $weekend,
                'workStartsAt' => $start, 'workEndsAt' => $end, 'geofence' => $geofence,
                'address' => ['city' => $city], 'costCenterId' => $this->ids[$costCenter],
            ]))->ulid;
        }
    }

    /**
     * Phase 2 demo: Riyadh gets its own lateness and overtime rules on top of the neutral default,
     * New York observes the US federal calendar, and Riyadh a sample Saudi calendar (the Eid dates
     * are illustrative; real ones are imported each year after moon sighting).
     */
    private function policies(): void
    {
        $set = resolve(SavePolicySet::class)->handle($this->actor, ['en' => 'Riyadh HQ rules', 'ar' => 'قواعد المقر الرئيسي'], ['en' => 'Demo overrides for the Riyadh head office.', 'ar' => 'قيم تجريبية للمقر الرئيسي في الرياض.']);
        resolve(SetPolicyValues::class)->handle($this->actor, $set, [
            'attendance.grace' => ['lateMinutes' => 10, 'earlyLeaveMinutes' => 5],
            'overtime.rules' => ['weekday' => 1.5, 'weekend' => 2.0, 'holiday' => 2.0, 'night' => 2.0],
        ], SeedPolicyDefaults::SINCE, 'Demo data');
        resolve(AssignPolicySet::class)->handle($this->actor, $set, PolicyLevel::Branch, Branch::query()->where('ulid', $this->ids['RUH-HQ'])->firstOrFail()->id, SeedPolicyDefaults::SINCE, 'Demo data');

        resolve(InstallHolidayPreset::class)->handle($this->actor, HolidayPresets::US_FEDERAL, [$this->ids['NYC']]);

        $saudi = resolve(SaveHolidayCalendar::class)->handle($this->actor, new HolidayCalendarInputData('SA_DEMO', ['en' => 'Saudi holidays (sample)', 'ar' => 'العطلات السعودية (نموذج)'], 'SA', true, [$this->ids['RUH-HQ']]));
        $rules = resolve(SaveHolidayRule::class);

        foreach ([
            ['name' => ['en' => 'Founding Day', 'ar' => 'يوم التأسيس'], 'type' => 'fixed', 'month' => 2, 'day' => 22, 'fromYear' => 2022],
            ['name' => ['en' => 'Saudi National Day', 'ar' => 'اليوم الوطني'], 'type' => 'fixed', 'month' => 9, 'day' => 23],
            ['name' => ['en' => 'Eid al-Fitr', 'ar' => 'عيد الفطر'], 'type' => 'one_off', 'date' => '2026-03-20', 'durationDays' => 4, 'hijriBased' => true],
            ['name' => ['en' => 'Eid al-Adha', 'ar' => 'عيد الأضحى'], 'type' => 'one_off', 'date' => '2026-05-26', 'durationDays' => 4, 'hijriBased' => true],
        ] as $rule) {
            $rules->handle($saudi, HolidayRuleInputData::from($rule));
        }
    }

    private function departments(): void
    {
        $departments = [
            ['EXEC', 'Executive Office', 'المكتب التنفيذي', null],
            ['OPS', 'Operations', 'العمليات', null],
            ['OPS-STORE', 'Store Operations', 'عمليات المتاجر', 'OPS'],
            ['OPS-LOG', 'Logistics', 'الخدمات اللوجستية', 'OPS'],
            ['PEOPLE', 'People & Culture', 'الموارد البشرية', null],
            ['FIN', 'Finance', 'المالية', null],
            ['SALES', 'Sales', 'المبيعات', null],
            ['TECH', 'Technology', 'التقنية', null],
        ];

        foreach ($departments as [$code, $en, $ar, $parent]) {
            $this->ids[$code] = resolve(SaveDepartment::class)->handle(new DepartmentInputData(
                $code, ['en' => $en, 'ar' => $ar], $parent === null ? null : $this->ids[$parent],
            ))->ulid;
        }
    }

    private function positions(): void
    {
        $positions = [
            ['CEO', 'Chief Executive Officer', 'الرئيس التنفيذي', 'EXEC', 'G8'],
            ['HEAD', 'Head of Department', 'رئيس القسم', null, 'G7'],
            ['MGR', 'Manager', 'مدير', null, 'G6'],
            ['LEAD', 'Team Lead', 'قائد فريق', null, 'G5'],
            ['HR-SPEC', 'HR Specialist', 'أخصائي موارد بشرية', 'PEOPLE', 'G4'],
            ['ACCT', 'Accountant', 'محاسب', 'FIN', 'G4'],
            ['STORE-ASSOC', 'Store Associate', 'موظف مبيعات متجر', 'OPS-STORE', 'G2'],
            ['DRIVER', 'Delivery Driver', 'سائق توصيل', 'OPS-LOG', 'G2'],
            ['SALES-REP', 'Sales Representative', 'مندوب مبيعات', 'SALES', 'G3'],
            ['ENGINEER', 'Software Engineer', 'مهندس برمجيات', 'TECH', 'G5'],
        ];

        foreach ($positions as [$code, $en, $ar, $department, $grade]) {
            $this->ids["POS-{$code}"] = resolve(SavePosition::class)->handle(new PositionInputData(
                $code, ['en' => $en, 'ar' => $ar], $department === null ? null : $this->ids[$department], $this->ids[$grade],
            ))->ulid;
        }
    }

    private function customFields(): void
    {
        $save = resolve(SaveCustomFieldDefinition::class);
        $save->handle(CustomFieldDefinitionInputData::from([
            'key' => 'tshirt_size', 'label' => ['en' => 'T-shirt size', 'ar' => 'مقاس القميص'], 'type' => 'select',
            'options' => array_map(fn (string $size): array => ['value' => mb_strtolower($size), 'label' => ['en' => $size, 'ar' => $size]], ['S', 'M', 'L', 'XL']),
        ]));
        $save->handle(CustomFieldDefinitionInputData::from([
            'key' => 'medical_note', 'label' => ['en' => 'Medical note', 'ar' => 'ملاحظة طبية'], 'type' => 'textarea', 'isSensitive' => true,
        ]));
    }

    private function people(): void
    {
        $ceo = $this->hire('RUH-HQ', 'EXEC', 'POS-CEO', null, '2018-02-01', 'G8');
        $heads = [
            'OPS' => $this->hire('RUH-HQ', 'OPS', 'POS-HEAD', $ceo, '2019-03-10', 'G7'),
            'PEOPLE' => $this->hire('RUH-HQ', 'PEOPLE', 'POS-HEAD', $ceo, '2019-06-01', 'G7', account: 'hr_admin'),
            'FIN' => $this->hire('RUH-HQ', 'FIN', 'POS-HEAD', $ceo, '2020-01-15', 'G7'),
            'SALES' => $this->hire('DXB', 'SALES', 'POS-HEAD', $ceo, '2020-09-01', 'G7'),
            'TECH' => $this->hire('NYC', 'TECH', 'POS-HEAD', $ceo, '2021-04-12', 'G7', western: true, account: 'department_manager'),
        ];

        // [branch, department, head, staff position, staff, team leads, western names, manager's sign-in]
        $units = [
            ['RUH-HQ', 'OPS-STORE', 'OPS', 'POS-STORE-ASSOC', 32, 2, false, null],
            ['RUH-HQ', 'OPS-LOG', 'OPS', 'POS-DRIVER', 10, 1, false, null],
            ['RUH-HQ', 'PEOPLE', 'PEOPLE', 'POS-HR-SPEC', 4, 0, false, null],
            ['RUH-HQ', 'FIN', 'FIN', 'POS-ACCT', 4, 0, false, null],
            ['DXB', 'SALES', 'SALES', 'POS-SALES-REP', 17, 1, false, 'branch_manager'],
            ['DXB', 'OPS-STORE', 'OPS', 'POS-STORE-ASSOC', 13, 1, false, null],
            ['NYC', 'TECH', 'TECH', 'POS-ENGINEER', 11, 1, true, null],
            ['NYC', 'FIN', 'FIN', 'POS-ACCT', 2, 0, true, null],
        ];

        $first = true;

        foreach ($units as $u => [$branch, $department, $head, $position, $staff, $leads, $western, $managerAccount]) {
            $manager = $this->hire($branch, $department, 'POS-MGR', $heads[$head], $this->date($u * 7), 'G6', western: $western, account: $managerAccount);
            $leaders = [];

            for ($l = 0; $l < $leads; $l++) {
                $leaders[] = $this->hire($branch, $department, 'POS-LEAD', $manager, $this->date($u * 7 + $l + 1), 'G5', western: $western, account: $first && $l === 0 ? 'team_lead' : null);
            }

            for ($s = 0; $s < $staff; $s++) {
                $reportsTo = $leaders === [] ? $manager : $leaders[$s % count($leaders)];
                $role = match (true) {
                    $first && $s === 0 => 'employee',
                    $department === 'PEOPLE' && $s === 0 => 'hr_officer',
                    default => null,
                };
                $employee = $this->hire($branch, $department, $position, $reportsTo, $this->date($u * 13 + $s * 5), $position === 'POS-ENGINEER' ? 'G5' : 'G2', western: $western, account: $role);

                if ($first && $s === 3) {
                    // Covers weekend shifts in Dubai too (secondary placement, D25).
                    resolve(AddSecondaryAssignment::class)->handle($this->actor, $employee, new PlacementInputData($this->ids['DXB'], $this->ids['OPS-STORE']), '2025-01-01');
                }
            }

            $first = false;
        }

        $this->hire('RUH-HQ', 'OPS-STORE', 'POS-STORE-ASSOC', $heads['OPS'], CarbonImmutable::now()->addDays(10)->toDateString(), 'G2');
        $leaver = $this->hire('DXB', 'SALES', 'POS-SALES-REP', $heads['SALES'], '2022-05-01', 'G3');
        resolve(TerminateEmployee::class)->handle($this->actor, $leaver, CarbonImmutable::now()->subMonth()->toDateString(), 'Resigned');

        $this->documents();
    }

    private function hire(string $branch, string $department, string $position, ?Employee $manager, string $hireDate, string $grade, bool $western = false, ?string $account = null): Employee
    {
        $name = $western ? DemoNames::western($this->nameIndex++) : DemoNames::arabic($this->nameIndex++);
        $country = match ($branch) {
            'DXB' => 'AE',
            'NYC' => 'US',
            default => 'SA',
        };
        $email = $account === null ? null : self::ACCOUNTS[$account];

        $employee = resolve(HireEmployee::class)->handle($this->actor, HireEmployeeData::from(array_filter([
            'nameEn' => $name['en'],
            'nameAr' => $name['ar'],
            'workEmail' => $email ?? strtolower(str_replace([' ', "'"], ['.', ''], $name['en'])).'.'.$this->nameIndex.'@mawred.test',
            'workPhone' => $this->phone($country),
            'gender' => $name['gender'],
            'nationality' => $country,
            'hireDate' => $hireDate,
            'probationEndsOn' => CarbonImmutable::parse($hireDate)->addMonths(3)->toDateString(),
            'placement' => array_filter([
                'branchId' => $this->ids[$branch],
                'departmentId' => $this->ids[$department],
                'positionId' => $this->ids[$position],
                'managerId' => $manager?->ulid,
                'employmentTypeId' => $this->ids[$position === 'POS-DRIVER' ? 'CT' : 'FT'],
                'gradeId' => $this->ids[$grade],
            ]),
        ], fn (mixed $v): bool => $v !== null)));

        $rank = (int) substr($grade, 1);
        $currency = match ($country) {
            'AE' => 'AED',
            'US' => 'USD',
            default => 'SAR',
        };
        resolve(RecordSalaryChange::class)->handle($this->actor, $employee, Money::fromDecimal(4000 + $rank * 2500 + ($this->nameIndex % 7) * 250, $currency), $hireDate, 'Starting salary');
        resolve(UpdateSensitiveDetails::class)->handle($this->actor, $employee, SensitiveDetailsInputData::from([
            'nationalId' => ['number' => sprintf('%d%09d', $country === 'SA' ? 1 : 2, 100_000_000 + $this->nameIndex * 7919)],
            'dateOfBirth' => CarbonImmutable::parse('1975-01-01')->addDays($this->nameIndex * 97 % 9000)->toDateString(),
        ]));
        resolve(SetCustomFieldValues::class)->handle($this->actor, $employee, ['tshirt_size' => ['s', 'm', 'l', 'xl'][$this->nameIndex % 4]]);

        if ($account !== null) {
            $this->accounts[$account] = $employee;
        }

        return $employee;
    }

    private function phone(string $country): string
    {
        $n = $this->phone++;

        return match ($country) {
            'AE' => sprintf('050%07d', 1_000_000 + $n),
            'US' => sprintf('212555%04d', 100 + ($n % 100)),
            default => sprintf('055%07d', 1_000_000 + $n),
        };
    }

    private function date(int $offset): string
    {
        return CarbonImmutable::parse('2021-01-04')->addDays($offset * 9)->toDateString();
    }

    private function documents(): void
    {
        $residency = DocumentType::query()->where('code', 'RESIDENCY')->first();
        $contract = DocumentType::query()->where('code', 'CONTRACT')->first();

        if ($residency === null || $contract === null) {
            return;
        }

        $path = storage_path('app/tmp/demo-document.pdf');
        @mkdir(dirname($path), 0755, true);
        file_put_contents($path, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");

        foreach (Employee::query()->where('nationality', '!=', 'SA')->orderBy('id')->limit(6)->get() as $i => $employee) {
            $copy = storage_path("app/tmp/demo-document-{$i}.pdf");
            copy($path, $copy);
            resolve(SaveEmployeeDocument::class)->handle($this->actor, $employee, new EmployeeDocumentInputData(
                $residency->ulid,
                sprintf('RP%07d', 4_000_000 + $i),
                CarbonImmutable::now()->subYear()->toDateString(),
                CarbonImmutable::now()->addDays([5, 20, 45, 90, 200, -3][$i])->toDateString(),
            ), new UploadedFile($copy, 'residency.pdf', 'application/pdf', null, true));
        }

        foreach (Employee::query()->orderBy('id')->limit(10)->get() as $i => $employee) {
            $copy = storage_path("app/tmp/demo-contract-{$i}.pdf");
            copy($path, $copy);
            resolve(SaveEmployeeDocument::class)->handle($this->actor, $employee, new EmployeeDocumentInputData(
                $contract->ulid, null, $employee->hire_date->toDateString(),
            ), new UploadedFile($copy, 'contract.pdf', 'application/pdf', null, true));
        }
    }

    private function accounts(string $password): void
    {
        foreach (self::ACCOUNTS as $role => $email) {
            $employee = $this->accounts[$role] ?? null;

            $user = User::query()->forceCreate([
                'name' => $employee instanceof Employee ? $employee->name_en : $email,
                'email' => $email,
                'password' => $password,
                'status' => 'active',
                'email_verified_at' => now(),
                'locale' => $role === 'employee' ? 'ar' : 'en',
            ]);

            resolve(AssignRoles::class)->handle($this->actor, $user, [$role => [], ...($role === 'employee' ? [] : ['employee' => []])]);

            if ($employee !== null) {
                resolve(LinkUserAccount::class)->handle($employee, $user);
            }
        }
    }
}
