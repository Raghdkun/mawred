<?php

declare(strict_types=1);

namespace App\Domain\People\Actions;

use App\Domain\Access\Support\ScopeResolver;
use App\Domain\People\Enums\ImportRowStatus;
use App\Domain\People\Enums\ImportStatus;
use App\Domain\People\Models\Import;
use App\Domain\People\Models\ImportRow;
use App\Domain\People\Support\EmployeeNumbers;
use App\Domain\People\Support\FieldGuard;
use App\Domain\People\Support\Imports\EmployeeRowValidator;
use App\Domain\People\Support\Imports\ImportErrorReport;
use App\Domain\People\Support\Imports\ImportLookups;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use App\Support\Spreadsheets\SpreadsheetReader;
use Illuminate\Database\ConnectionInterface;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Reads the file in chunks of 500, validates every row, links managers that appear in the same
 * file and orders rows so managers are created first. Produces the dry-run counts and, when
 * rows need fixing, an XLSX error report.
 */
final readonly class ValidateImport
{
    public const int CHUNK = 500;

    public const int MAX_ROWS = 20_000;

    public function __construct(private ConnectionInterface $db, private FieldGuard $guard, private ScopeResolver $resolver) {}

    public function handle(Import $import, Actor $actor): Import
    {
        $import->forceFill(['status' => ImportStatus::Validating])->save();
        $import->rows()->delete();
        $import->clearMediaCollection('error_report');

        $source = $import->getFirstMedia('source');

        if (! $source instanceof Media) {
            throw new DomainRuleViolation('people.import_file_missing', __('The uploaded file is missing. Upload it again.'));
        }

        $validator = new EmployeeRowValidator($actor, new ImportLookups, $this->guard, $this->resolver);
        $buffer = [];
        $total = 0;

        foreach (SpreadsheetReader::rows($source->getPath(), $source->extension) as $number => $cells) {
            if ($number === 1 || array_filter($cells, fn (?string $c): bool => $c !== null) === []) {
                continue;
            }

            if (++$total > self::MAX_ROWS) {
                throw new DomainRuleViolation('people.import_too_large', __('Imports are limited to :max rows. Split the file and try again.', ['max' => self::MAX_ROWS]));
            }

            $buffer[$number] = $cells;

            if (count($buffer) === self::CHUNK) {
                $this->store($import, $validator, $buffer);
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            $this->store($import, $validator, $buffer);
        }

        $this->linkManagers($import);

        return $this->summarise($import);
    }

    /**
     * @param  array<int, list<string|null>>  $rows
     */
    private function store(Import $import, EmployeeRowValidator $validator, array $rows): void
    {
        $insert = [];

        foreach ($validator->chunk($rows, $import->mapping) as $number => $result) {
            $insert[] = [
                'import_id' => $import->id,
                'row_number' => $number,
                'raw' => json_encode($rows[$number], JSON_THROW_ON_ERROR),
                'normalized' => $result['normalized'] === null ? null : json_encode($result['normalized'], JSON_THROW_ON_ERROR),
                'errors' => $result['errors'] === [] ? null : json_encode($result['errors'], JSON_THROW_ON_ERROR),
                'status' => $result['errors'] === [] ? ImportRowStatus::Valid->value : ImportRowStatus::Invalid->value,
            ];
        }

        $this->db->table('import_rows')->insert($insert);
    }

    /**
     * Managers referenced by number/email that are new in this file: point at their row, refuse
     * unknown ones, invalid ones and loops, and give every row a level (its manager's level + 1).
     */
    private function linkManagers(Import $import): void
    {
        /** @var array<int, ImportRow> $rows */
        $rows = ImportRow::query()->where('import_id', $import->id)->get(['id', 'row_number', 'raw', 'normalized', 'status'])->keyBy('row_number')->all();
        $columns = array_flip($import->mapping);
        $keys = [];

        foreach ($rows as $number => $row) {
            // Identifiers come from the raw cells so a manager row with other errors is still found.
            foreach (['number' => 'employee_number', 'email' => 'work_email'] as $kind => $field) {
                $cell = isset($columns[$field]) ? ($row->raw[(int) $columns[$field]] ?? null) : null;

                if (is_string($cell) && trim($cell) !== '') {
                    $keys[$kind.':'.($kind === 'number' ? EmployeeNumbers::normalise($cell) : mb_strtolower(trim($cell)))] = $number;
                }
            }
        }

        $parent = [];
        $invalid = [];

        foreach ($rows as $number => $row) {
            $manager = $row->status === ImportRowStatus::Valid ? ($row->normalized['manager'] ?? null) : null;
            $key = is_array($manager) ? ($manager['key'] ?? null) : null;

            if (! is_string($key)) {
                continue;
            }

            $target = $keys[$key] ?? null;

            if ($target === null) {
                $invalid[$number] = __('No employee or row in this file matches this manager.');
            } elseif ($target === $number) {
                $invalid[$number] = __('Someone cannot report to themselves or to a person in their own team.');
            } else {
                $parent[$number] = $target;
            }
        }

        // Walk each chain once: loops and chains that reach a row with errors are refused.
        $levels = [];

        foreach (array_keys($parent) as $start) {
            $path = [];
            $current = $start;

            while (isset($parent[$current]) && ! isset($levels[$current]) && ! in_array($current, $path, true)) {
                $path[] = $current;
                $current = $parent[$current];
            }

            if (in_array($current, $path, true)) {
                foreach ($path as $member) {
                    $invalid[$member] ??= __('These rows report to each other in a loop.');
                }

                continue;
            }

            $base = $levels[$current] ?? 0;
            $blocked = isset($invalid[$current]) || ($rows[$current]->status ?? null) !== ImportRowStatus::Valid;

            foreach (array_reverse($path) as $member) {
                $base++;
                $levels[$member] = $base;

                if ($blocked) {
                    $invalid[$member] ??= __('This manager\'s row has errors. Fix it first.');
                }
            }
        }

        foreach ($parent as $number => $managerRow) {
            if (isset($invalid[$number])) {
                continue;
            }

            $normalized = $rows[$number]->normalized ?? [];
            $normalized['manager'] = ['row' => $managerRow];
            $rows[$number]->forceFill(['normalized' => $normalized, 'level' => min($levels[$number] ?? 0, 65_000)])->save();
        }

        foreach ($invalid as $number => $message) {
            $rows[$number]->forceFill(['status' => ImportRowStatus::Invalid, 'normalized' => null, 'errors' => [...($rows[$number]->errors ?? []), 'manager' => [$message]]])->save();
        }
    }

    private function summarise(Import $import): Import
    {
        $counts = $this->db->table('import_rows')
            ->where('import_id', $import->id)
            ->selectRaw('count(*) as total')
            ->selectRaw("count(*) filter (where status = 'valid') as valid")
            ->selectRaw("count(*) filter (where status = 'invalid') as invalid")
            ->selectRaw("count(*) filter (where status = 'valid' and normalized->>'action' = 'create') as creates")
            ->selectRaw("count(*) filter (where status = 'valid' and normalized->>'action' = 'update') as updates")
            ->first();

        $import->forceFill([
            'status' => ImportStatus::Validated,
            'total_rows' => $this->count($counts, 'total'),
            'valid_rows' => $this->count($counts, 'valid'),
            'invalid_rows' => $this->count($counts, 'invalid'),
            'to_create' => $this->count($counts, 'creates'),
            'to_update' => $this->count($counts, 'updates'),
            'validated_at' => now(),
        ])->save();

        if ($import->invalid_rows > 0) {
            ImportErrorReport::write($import);
        }

        return $import;
    }

    private function count(?object $counts, string $column): int
    {
        $value = $counts->{$column} ?? 0;

        return is_numeric($value) ? (int) $value : 0;
    }
}
