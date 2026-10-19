<?php

declare(strict_types=1);

namespace App\Domain\Reports\Support;

use App\Domain\Reports\Contracts\Report;
use App\Domain\Reports\Enums\ColumnType;
use App\Domain\Reports\Enums\ReportFormat;
use App\Domain\Setup\Support\CompanyBrand;
use App\Support\Errors\DomainRuleViolation;
use App\Support\Pdf\Gotenberg;
use App\Support\Spreadsheets\TableWriter;
use Generator;

/**
 * Writes a report's rows to a file: XLSX and CSV stream any number of rows; a PDF is a printable
 * landscape table, so it stops at a size a person would print.
 */
final readonly class ReportFile
{
    public const int PDF_MAX_ROWS = 2000;

    public function __construct(private Gotenberg $gotenberg, private CompanyBrand $brand) {}

    /**
     * @param  iterable<array<string, mixed>>  $rows
     * @param  array{period: string, generated: string}  $meta  printed under the title of a PDF
     * @return int the number of rows written
     */
    public function write(ReportFormat $format, string $path, Report $report, iterable $rows, string $locale, array $meta): int
    {
        $columns = $report->columns();
        $header = array_map(fn (ReportColumn $c): string => $c->labelIn($locale), $columns);
        $title = $report->title()[$locale === 'ar' ? 'ar' : 'en'];

        return match ($format) {
            ReportFormat::Xlsx => TableWriter::xlsx($path, $header, $this->cells($columns, $rows), $locale === 'ar', $title),
            ReportFormat::Csv => TableWriter::csv($path, $header, $this->cells($columns, $rows)),
            ReportFormat::Pdf => $this->pdf($path, $title, $header, $columns, $rows, $locale, $meta),
        };
    }

    /**
     * @param  list<ReportColumn>  $columns
     * @param  iterable<array<string, mixed>>  $rows
     * @return Generator<int, list<string|int|float|null>>
     */
    private function cells(array $columns, iterable $rows): Generator
    {
        foreach ($rows as $row) {
            yield array_map(fn (ReportColumn $c): string|int|float|null => $this->cell($row[$c->key] ?? null, $c->type), $columns);
        }
    }

    private function cell(mixed $value, ColumnType $type): string|int|float|null
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            ColumnType::Number, ColumnType::Minutes => is_numeric($value) ? (int) $value : null,
            ColumnType::Decimal, ColumnType::Percent => is_numeric($value) ? (float) $value : null,
            default => is_scalar($value) ? (string) $value : null,
        };
    }

    /**
     * @param  list<string>  $header
     * @param  list<ReportColumn>  $columns
     * @param  iterable<array<string, mixed>>  $rows
     * @param  array{period: string, generated: string}  $meta
     */
    private function pdf(string $path, string $title, array $header, array $columns, iterable $rows, string $locale, array $meta): int
    {
        $e = fn (string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $numeric = fn (ColumnType $type): bool => $type !== ColumnType::Text && $type !== ColumnType::Date;
        $body = '';
        $count = 0;

        foreach ($this->cells($columns, $rows) as $cells) {
            if (++$count > self::PDF_MAX_ROWS) {
                throw new DomainRuleViolation('reports.pdf_too_long', __('This report has more than :count rows, too many to print. Export it as XLSX or CSV instead.', ['count' => self::PDF_MAX_ROWS], $locale));
            }

            $body .= '<tr>';

            foreach ($cells as $index => $cell) {
                $value = $cell === null ? '' : (is_float($cell) ? number_format($cell, 2, '.', ',') : (string) $cell);
                $body .= '<td'.($numeric($columns[$index]->type) ? ' class="n"' : '').'>'.$e($value).'</td>';
            }

            $body .= '</tr>';
        }

        $dir = $locale === 'ar' ? 'rtl' : 'ltr';
        $head = implode('', array_map(fn (string $h): string => '<th>'.$e($h).'</th>', $header));
        $html = '<!doctype html><html lang="'.$e($locale).'"><head><meta charset="utf-8"><style>'
            .'body{font-family:"IBM Plex Sans Arabic","Noto Sans Arabic","IBM Plex Sans","Noto Sans",Arial,sans-serif;color:#182238;font-size:9pt}'
            .'header{margin-bottom:10pt}h1{font-size:15pt;margin:0 0 3pt}p{margin:0;color:#4a5670}'
            .'table{width:100%;border-collapse:collapse}th,td{padding:4pt 5pt;border-bottom:0.5pt solid #d5dae3;text-align:start;vertical-align:top}'
            .'th{background:#eef1f6;font-weight:600}td.n{text-align:end;font-variant-numeric:tabular-nums}thead{display:table-header-group}tr{page-break-inside:avoid}'
            .'</style></head><body dir="'.$dir.'"><header><p>'.$e($this->brand->name($locale)).'</p><h1>'.$e($title).'</h1><p>'.$e($meta['period']).' · '.$e($meta['generated']).'</p></header>'
            .'<table><thead><tr>'.$head.'</tr></thead><tbody>'.$body.'</tbody></table></body></html>';

        file_put_contents($path, $this->gotenberg->html($html, landscape: true, margin: '0.4'));

        return $count;
    }
}
