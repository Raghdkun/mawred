<?php

declare(strict_types=1);

namespace Database\Seeders\Production;

use App\Domain\Access\Actions\SeedDefaultRoles;
use App\Domain\Organization\Actions\SeedOrganizationDefaults;
use App\Domain\People\Actions\SeedPeopleDefaults;
use Illuminate\Database\Seeder;

/**
 * Production seed (brief §21): roles and permissions, default employment types and document
 * types. The default policy preset, request types and templates join in their phases. Idempotent.
 */
final class ProductionSeeder extends Seeder
{
    public function run(SeedDefaultRoles $roles, SeedOrganizationDefaults $organization, SeedPeopleDefaults $people): void
    {
        $roles->handle();
        $organization->handle();
        $people->handle();
    }
}
