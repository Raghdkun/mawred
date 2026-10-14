<?php

declare(strict_types=1);

namespace App\Domain\People\Support\Imports;

use App\Domain\People\Enums\ImportRowStatus;
use App\Domain\People\Models\Import;
use App\Domain\People\Models\ImportRow;
use App\Support\Spreadsheets\SpreadsheetWriter;
use Generator;
use Illuminate\Support\Facades\File;

/**
 * XLSX of the rows that need fixing: the original columns, then the row number and the errors,
 * so HR can correct the file and upload it again.
 */
final class ImportErrorReport
{
    public static function write(Import $import): void
    {
        $path = storage_path('app/tmp/import-errors-'.$import->ulid.'.xlsx');
        File::ensureDirectoryExists(dirname($path));

        $header = [...array_map(fn (?string $h): string => $h ?? '', $import->headers), __('Row'), __('Errors')];

        SpreadsheetWriter::write($path, array_map(fn (mixed $h): string => is_string($h) ? $h : '', $header), self::rows($import));

        $import->addMedia($path)->usingFileName("errors-{$import->ulid}.xlsx")->toMediaCollection('error_report');
    }

    /**
     * @return Generator<int, list<string|null>>
     */
    private static function rows(Import $import): Generator
    {
        $query = ImportRow::query()
            ->where('import_id', $import->id)
            ->whereIn('status', [ImportRowStatus::Invalid, ImportRowStatus::Failed])
            ->orderBy('row_number');

        foreach ($query->lazyById(500) as $row) {
            $messages = [];

            foreach ($row->errors ?? [] as $field => $list) {
                foreach ($list as $message) {
                    $messages[] = "{$field}: {$message}";
                }
            }

            $cells = array_pad($row->raw, count($import->headers), null);

            yield [...$cells, (string) $row->row_number, implode(' | ', $messages)];
        }
    }
}
