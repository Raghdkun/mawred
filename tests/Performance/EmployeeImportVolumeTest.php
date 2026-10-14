<?php

declare(strict_types=1);

use App\Domain\Organization\Models\Branch;
use App\Domain\People\Actions\SaveImportMapping;
use App\Domain\People\Actions\StartEmployeeImport;
use App\Domain\People\Actions\StartImportCommit;
use App\Domain\People\Enums\EmployeeStatus;
use App\Domain\People\Models\Employee;
use App\Support\Actors\Actor;
use App\Support\Spreadsheets\SpreadsheetWriter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
| Runs outside a wrapping transaction (DatabaseTruncation), like a real queue worker: each row
| commits on its own. Slow by design; not part of `composer test`.
*/
it('imports 10,000 rows in chunks', function (): void {
    seedAccess();
    Storage::fake('local');
    $hr = userWithRoles('hr_admin');
    Branch::factory()->riyadh()->create(['code' => 'RUH']);

    $rows = [];
    for ($i = 1; $i <= 10_000; $i++) {
        $rows[] = [sprintf('BULK-%05d', $i), "Person {$i}", "bulk{$i}@mawred.test", '2025-01-01', 'RUH', $i > 1 ? 'BULK-00001' : null];
    }

    $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
    SpreadsheetWriter::write($path, ['Employee number', 'Name', 'Email', 'Hire date', 'Branch', 'Manager'], $rows);

    $started = microtime(true);
    $import = resolve(StartEmployeeImport::class)->handle(Actor::user($hr), new UploadedFile($path, 'bulk.xlsx', null, null, true));
    resolve(SaveImportMapping::class)->handle($import, $import->mapping);
    $import->refresh();
    $validated = microtime(true);

    expect($import->valid_rows)->toBe(10_000);

    resolve(StartImportCommit::class)->handle($import);

    expect($import->fresh()?->created_rows)->toBe(10_000)
        ->and(Employee::query()->where('status', EmployeeStatus::Active)->count())->toBe(10_000)
        ->and(Employee::query()->where('employee_number', 'BULK-09999')->sole()->currentManager?->employee_number)->toBe('BULK-00001');

    fwrite(STDERR, sprintf("\n10k import: validate %.1fs, commit %.1fs\n", $validated - $started, microtime(true) - $validated));
});
