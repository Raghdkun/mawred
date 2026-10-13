<?php

declare(strict_types=1);

namespace App\Domain\People\Support;

use App\Domain\Setup\Support\Settings;
use Illuminate\Database\ConnectionInterface;

/**
 * Employee numbers are `<prefix><6 digits>`, e.g. `EMP-000123` (D38). The prefix is the
 * `people.number_prefix` setting; the digits come from a Postgres sequence, so numbers are never
 * reused, even after a rollback or an archived employee.
 */
final readonly class EmployeeNumbers
{
    public const string DEFAULT_PREFIX = 'EMP-';

    public function __construct(private ConnectionInterface $db, private Settings $settings) {}

    public function next(): string
    {
        $prefix = $this->settings->get('people.number_prefix', self::DEFAULT_PREFIX);
        $value = $this->db->scalar("select nextval('employee_number_seq')");

        return (is_string($prefix) ? $prefix : self::DEFAULT_PREFIX).str_pad((string) (is_numeric($value) ? (int) $value : 0), 6, '0', STR_PAD_LEFT);
    }

    public static function normalise(string $number): string
    {
        return mb_strtoupper(trim($number));
    }
}
