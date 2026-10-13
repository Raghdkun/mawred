# ADR-0021: XLSX/CSV via OpenSpout

- Status: Proposed
- Date: 2026-10-13

## Decision
**openspout/openspout 5** (PHP ≥ 8.4) streams reads and writes row by row in roughly constant memory. It is used for the employee/holiday/roster imports (chunked into queued jobs of 500 rows), the error reports, report exports and the monthly attendance sheet. A thin `Support\Spreadsheet` wrapper provides typed column maps, header detection, and an RTL sheet direction for Arabic exports.

## Alternatives
maatwebsite/excel 4 (on PhpSpreadsheet 5) has more features (styling, formulas) but uses much more memory per chunk. We don't need formulas. Styled exports can still be added later if needed, using PhpSpreadsheet for small files only.
