<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Support;

use Illuminate\Database\ConnectionInterface;

/** Requisition numbers `JR-00042` from a Postgres sequence: never reused, even after a rollback. */
final readonly class RequisitionNumbers
{
    public function __construct(private ConnectionInterface $db) {}

    public function next(): string
    {
        $value = $this->db->scalar("select nextval('job_requisition_number_seq')");

        return 'JR-'.str_pad((string) (is_numeric($value) ? (int) $value : 0), 5, '0', STR_PAD_LEFT);
    }
}
