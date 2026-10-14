<?php

declare(strict_types=1);

namespace Database\Seeders\Production;

use App\Domain\Access\Actions\SeedDefaultRoles;
use App\Domain\Organization\Actions\SeedOrganizationDefaults;
use App\Domain\People\Actions\SeedPeopleDefaults;
use App\Domain\Policies\Actions\SeedPolicyDefaults;
use Illuminate\Database\Seeder;

/**
 * Production seed (brief §21): roles and permissions, default employment types, document types,
 * leave types and the Neutral default policy set. Request types and templates join in their
 * phases. Idempotent.
 */
final class ProductionSeeder extends Seeder
{
    public function run(SeedDefaultRoles $roles, SeedOrganizationDefaults $organization, SeedPeopleDefaults $people, SeedPolicyDefaults $policies): void
    {
        $roles->handle();
        $organization->handle();
        $people->handle();
        $policies->handle();
    }
}
