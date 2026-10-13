<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Access\Contracts\OrgScopePredicates;
use App\Domain\Access\Support\SensitiveFieldRegistry;
use App\Domain\People\Models\CustomFieldDefinition;
use App\Domain\People\Models\Employee;
use App\Domain\People\Policies\EmployeePolicy;
use App\Domain\People\Support\EmployeeScopePredicates;
use App\Domain\People\Support\SensitiveReads;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * People wiring: the real org-relative scope predicates (ADR-0005) behind the Access contract,
 * the employee policy and the per-request sensitive-read recorder.
 */
final class PeopleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OrgScopePredicates::class, EmployeeScopePredicates::class);
        // One per request (or job), so repeated reads of the same field are audited once.
        $this->app->scoped(SensitiveReads::class);
    }

    public function boot(): void
    {
        Gate::policy(Employee::class, EmployeePolicy::class);

        $this->app->make(SensitiveFieldRegistry::class)->extend(function (): array {
            $labels = [];

            foreach (CustomFieldDefinition::query()->where('is_sensitive', true)->orderBy('sort_order')->orderBy('id')->get() as $definition) {
                /** @var array<string, string> $label */
                $label = $definition->getTranslations('label');
                $labels[$definition->fieldKey()] = $label;
            }

            return $labels;
        });
    }
}
