<?php

declare(strict_types=1);

namespace App\Support\Spreadsheets;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Writes a single-sheet XLSX with a bold header row (templates, error reports, exports).
 */
final class SpreadsheetWriter
{
    /**
     * @param  list<string>  $header
     * @param  iterable<list<string|int|float|null>>  $rows
     */
    public static function write(string $path, array $header, iterable $rows): void
    {
        $writer = new Writer;
        $writer->openToFile($path);

        try {
            $writer->addRow(Row::fromValuesWithStyle($header, (new Style)->withFontBold(true)));

            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues($row));
            }
        } finally {
            $writer->close();
        }
    }
}
