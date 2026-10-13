<?php

declare(strict_types=1);

namespace Database\Seeders\Production;

use App\Domain\Access\Actions\SeedDefaultRoles;
use Illuminate\Database\Seeder;

/**
 * Production seed (brief §21): roles and permissions only for now. The default policy preset,
 * request types and templates join in their phases. Idempotent.
 */
final class ProductionSeeder extends Seeder
{
    public function run(SeedDefaultRoles $roles): void
    {
        $roles->handle();
    }
}
