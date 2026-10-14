<?php

declare(strict_types=1);

namespace App\Domain\Requests\Support;

use Illuminate\Database\ConnectionInterface;

/** Request numbers `REQ-000123` from a Postgres sequence: never reused, even after a rollback. */
final readonly class RequestNumbers
{
    public function __construct(private ConnectionInterface $db) {}

    public function next(): string
    {
        $value = $this->db->scalar("select nextval('request_number_seq')");

        return 'REQ-'.str_pad((string) (is_numeric($value) ? (int) $value : 0), 6, '0', STR_PAD_LEFT);
    }
}
