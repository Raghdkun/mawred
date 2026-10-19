<?php

declare(strict_types=1);

namespace App\Domain\Training\Support;

use Illuminate\Database\ConnectionInterface;

/** Course codes `TRN-0042` from a Postgres sequence: never reused, even after a rollback. */
final readonly class CourseCodes
{
    public function __construct(private ConnectionInterface $db) {}

    public function next(): string
    {
        $value = $this->db->scalar("select nextval('course_code_seq')");

        return 'TRN-'.str_pad((string) (is_numeric($value) ? (int) $value : 0), 4, '0', STR_PAD_LEFT);
    }
}
