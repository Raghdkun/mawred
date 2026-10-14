<?php

declare(strict_types=1);

namespace App\Support\Spreadsheets;

use DateTimeInterface;
use Generator;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Streams the first sheet of an XLSX or CSV file as plain string cells, so large files never
 * load into memory. Dates become `Y-m-d`; whole numbers lose Excel's trailing `.0`.
 */
final class SpreadsheetReader
{
    /**
     * @return Generator<int, list<string|null>> 1-based row number => cells
     */
    public static function rows(string $path, string $extension): Generator
    {
        $reader = self::open($path, $extension);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $number = 0;

                foreach ($sheet->getRowIterator() as $row) {
                    yield ++$number => array_map(self::cell(...), array_values($row->toArray()));
                }

                break;
            }
        } finally {
            $reader->close();
        }
    }

    /**
     * @return list<string|null>
     */
    public static function header(string $path, string $extension): array
    {
        foreach (self::rows($path, $extension) as $cells) {
            return $cells;
        }

        return [];
    }

    private static function open(string $path, string $extension): XlsxReader|CsvReader
    {
        // Empty rows are kept so row numbers match what people see in Excel.
        $reader = mb_strtolower($extension) === 'xlsx'
            ? new XlsxReader(new XlsxOptions(SHOULD_PRESERVE_EMPTY_ROWS: true))
            : new CsvReader(new CsvOptions(SHOULD_PRESERVE_EMPTY_ROWS: true, FIELD_DELIMITER: self::sniffDelimiter($path)));

        $reader->open($path);

        return $reader;
    }

    private static function cell(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_float($value) && floor($value) === $value && abs($value) < 1e15) {
            return (string) (int) $value;
        }

        if (is_scalar($value)) {
            $text = trim((string) $value);

            // Strip a UTF-8 BOM that Excel adds to the first CSV cell.
            $text = (string) preg_replace('/^\x{FEFF}/u', '', $text);

            return $text === '' ? null : $text;
        }

        return null;
    }

    /** Excel in many locales saves "CSV" with semicolons. */
    private static function sniffDelimiter(string $path): string
    {
        $handle = fopen($path, 'r');
        $line = $handle === false ? '' : (string) fgets($handle);

        if ($handle !== false) {
            fclose($handle);
        }

        return substr_count($line, ';') > substr_count($line, ',') ? ';' : ',';
    }
}
