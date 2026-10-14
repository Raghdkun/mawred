<?php

declare(strict_types=1);

namespace App\Domain\Policies\Actions;

use App\Domain\Policies\Data\HolidayRuleInputData;
use App\Domain\Policies\Enums\HolidayType;
use App\Domain\Policies\Models\HolidayCalendar;
use App\Domain\Policies\Models\HolidayRule;
use App\Support\Errors\DomainRuleViolation;
use App\Support\Spreadsheets\SpreadsheetReader;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Validator;

/**
 * Imports one-off holiday dates from CSV or XLSX: `date, name_en, name_ar, days, hijri_based`.
 * Used each year for moon-sighted Hijri holidays. All rows are checked before anything is saved;
 * rows matching an existing one-off (same date and English name) are skipped.
 */
final readonly class ImportHolidayDates
{
    public const array COLUMNS = ['date', 'name_en', 'name_ar', 'days', 'hijri_based'];

    private const int MAX_ROWS = 500;

    public function __construct(private ConnectionInterface $db, private SaveHolidayRule $rules) {}

    /**
     * @return array{imported: int, skipped: int}
     */
    public function handle(HolidayCalendar $calendar, string $path, string $extension): array
    {
        $inputs = [];
        $errors = [];
        $columns = null;

        foreach (SpreadsheetReader::rows($path, $extension) as $number => $cells) {
            if ($columns === null) {
                $columns = array_map(fn (?string $cell): string => mb_strtolower(trim((string) $cell)), $cells);

                if (array_diff(['date', 'name_en'], $columns) !== []) {
                    throw new DomainRuleViolation('holidays.import_columns', __('The file needs at least the columns date and name_en.'), ['file' => [__('The file needs at least the columns date and name_en.')]]);
                }

                continue;
            }

            if (array_filter($cells, fn (?string $cell): bool => $cell !== null && trim($cell) !== '') === []) {
                continue;
            }

            if (count($inputs) >= self::MAX_ROWS) {
                throw new DomainRuleViolation('holidays.import_too_large', __('Import up to :count dates at a time.', ['count' => self::MAX_ROWS]), ['file' => [__('Import up to :count dates at a time.', ['count' => self::MAX_ROWS])]]);
            }

            $row = [];

            foreach ($columns as $index => $column) {
                $row[$column] = isset($cells[$index]) ? trim($cells[$index]) : null;
            }

            $data = [
                'name' => ['en' => $row['name_en'] ?? null, 'ar' => ($row['name_ar'] ?? '') === '' ? null : $row['name_ar']],
                'type' => HolidayType::OneOff->value,
                'date' => $row['date'] ?? null,
                'durationDays' => ($row['days'] ?? '') === '' ? 1 : $row['days'],
                'hijriBased' => in_array(mb_strtolower($row['hijri_based'] ?? ''), ['1', 'yes', 'true', 'نعم'], true),
            ];
            $validator = Validator::make($data, HolidayRuleInputData::rules());

            if ($validator->fails()) {
                $errors["rows.{$number}"] = array_values($validator->errors()->all());

                continue;
            }

            $inputs[] = HolidayRuleInputData::from($data);
        }

        if ($errors !== []) {
            throw new DomainRuleViolation('holidays.import_invalid', __('Some rows need attention. Nothing was imported.'), $errors);
        }

        return $this->db->transaction(function () use ($calendar, $inputs): array {
            $imported = 0;

            foreach ($inputs as $input) {
                $exists = HolidayRule::query()
                    ->where('holiday_calendar_id', $calendar->id)
                    ->where('type', HolidayType::OneOff)
                    ->whereDate('date', (string) $input->date)
                    ->where('name->en', $input->name['en'])
                    ->exists();

                if (! $exists) {
                    $this->rules->handle($calendar, $input);
                    $imported++;
                }
            }

            return ['imported' => $imported, 'skipped' => count($inputs) - $imported];
        });
    }
}
