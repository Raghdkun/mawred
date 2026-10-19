<?php

declare(strict_types=1);

namespace Database\Seeders\Production;

use App\Domain\Access\Actions\SeedDefaultRoles;
use App\Domain\Hiring\Actions\SeedHiringDefaults;
use App\Domain\Organization\Actions\SeedOrganizationDefaults;
use App\Domain\People\Actions\SeedPeopleDefaults;
use App\Domain\Policies\Actions\SeedPolicyDefaults;
use App\Domain\Requests\Actions\SeedRequestTypes;
use App\Domain\Training\Actions\SeedTrainingDefaults;
use App\Domain\Workflows\Actions\SeedChecklists;
use Illuminate\Database\Seeder;

/**
 * Production seed (brief §21): roles and permissions, default employment types, document types,
 * leave types, the Neutral default policy set and the seeded request types with their forms and
 * workflows (D55, D56), the onboarding/offboarding checklists and the hiring defaults (default
 * pipeline and application form, D81) and the training enrolment workflow (D99). Idempotent.
 */
final class ProductionSeeder extends Seeder
{
    public function run(SeedDefaultRoles $roles, SeedOrganizationDefaults $organization, SeedPeopleDefaults $people, SeedPolicyDefaults $policies, SeedRequestTypes $requestTypes, SeedChecklists $checklists, SeedHiringDefaults $hiring, SeedTrainingDefaults $training): void
    {
        $roles->handle();
        $organization->handle();
        $people->handle();
        $policies->handle();
        $requestTypes->handle();
        $checklists->handle();
        $hiring->handle();
        $training->handle();
    }
}
