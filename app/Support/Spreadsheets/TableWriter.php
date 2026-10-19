<?php

declare(strict_types=1);

namespace App\Support\Spreadsheets;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\CSV\Options as CsvOptions;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options as XlsxOptions;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/**
 * Streams a table to XLSX (a bold, frozen header; right-to-left for Arabic) or CSV (UTF-8 with a
 * BOM so Excel reads Arabic). Text that a spreadsheet would run as a formula is neutralised.
 */
final class TableWriter
{
    /**
     * @param  list<string>  $header
     * @param  iterable<list<string|int|float|null>>  $rows
     * @return int rows written, header excluded
     */
    public static function xlsx(string $path, array $header, iterable $rows, bool $rightToLeft = false, ?string $sheetName = null): int
    {
        $writer = new XlsxWriter(new XlsxOptions(DEFAULT_COLUMN_WIDTH: 18));
        $writer->openToFile($path);
        $count = 0;

        try {
            $sheet = $writer->getCurrentSheet();
            $sheet->setSheetView(new SheetView(rightToLeft: $rightToLeft, freezeRow: 2));

            if ($sheetName !== null) {
                $sheet->setName(self::sheetName($sheetName));
            }

            $writer->addRow(Row::fromValuesWithStyle($header, (new Style)->withFontBold(true)));

            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues(array_map(self::safe(...), $row)));
                $count++;
            }
        } finally {
            $writer->close();
        }

        return $count;
    }

    /**
     * @param  list<string>  $header
     * @param  iterable<list<string|int|float|null>>  $rows
     * @return int rows written, header excluded
     */
    public static function csv(string $path, array $header, iterable $rows): int
    {
        $writer = new CsvWriter(new CsvOptions(SHOULD_ADD_BOM: true));
        $writer->openToFile($path);
        $count = 0;

        try {
            $writer->addRow(Row::fromValues($header));

            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues(array_map(self::safe(...), $row)));
                $count++;
            }
        } finally {
            $writer->close();
        }

        return $count;
    }

    /**
     * A cell a spreadsheet would treat as a formula (CSV injection) gets a leading apostrophe.
     */
    public static function safe(string|int|float|null $value): string|int|float|null
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    /** Excel sheet names: 31 characters at most, none of []:*?/\ */
    private static function sheetName(string $name): string
    {
        $clean = trim(str_replace(['[', ']', ':', '*', '?', '/', '\\'], ' ', $name));

        return mb_substr($clean === '' ? 'Report' : $clean, 0, 31);
    }
}
