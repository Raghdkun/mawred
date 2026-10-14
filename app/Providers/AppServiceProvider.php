<?php

namespace App\Providers;

use App\Domain\Organization\Support\BranchSearchProvider;
use App\Domain\Organization\Support\DepartmentSearchProvider;
use App\Domain\People\Support\EmployeeSearchProvider;
use App\Domain\Setup\Support\Settings;
use App\Support\Search\GlobalSearch;
use App\Support\Templates\SafeTemplate;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(Settings::class);
        $this->app->singleton(SafeTemplate::class);

        // Global search (ADR-0013): each domain contributes a scope-enforcing provider.
        $this->app->singleton(function (Application $app): GlobalSearch {
            $search = new GlobalSearch($app->make(Gate::class));
            $search->register($app->make(EmployeeSearchProvider::class));
            $search->register($app->make(BranchSearchProvider::class));
            $search->register($app->make(DepartmentSearchProvider::class));

            return $search;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // N+1 and silent attribute bugs fail loudly outside production (brief §3).
        Model::shouldBeStrict(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
