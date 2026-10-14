<?php

declare(strict_types=1);

use App\Domain\Access\Actions\AssignRoles;
use App\Domain\Access\Models\User;
use App\Domain\People\Actions\LinkUserAccount;
use App\Domain\People\Models\Employee;
use App\Support\Actors\Actor;
use Database\Seeders\Performance\PerformanceSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/*
| Brief §19: list pages answer in under 300 ms server time at 5,000 employees, with a fixed number
| of queries (no N+1), for every scope shape.
*/

it('lists, filters and searches 5,000 employees fast for every scope', function (): void {
    $this->seed(PerformanceSeeder::class);
    expect(Employee::query()->count())->toBe(5000);

    $viewers = [];
    foreach (['hr_admin' => 'PERF-000001', 'hr_officer' => 'PERF-000101', 'team_lead' => 'PERF-000202'] as $role => $number) {
        $user = User::factory()->create(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()]);
        resolve(AssignRoles::class)->handle(Actor::system('test'), $user, [$role => []]);
        resolve(LinkUserAccount::class)->handle(Employee::query()->where('employee_number', $number)->sole(), $user);
        $viewers[$role] = $user->fresh();
    }

    $endpoints = [
        'list' => '/api/v1/employees?per_page=25',
        'second page' => null,
        'filtered' => '/api/v1/employees?per_page=25&sort=hire_date&status=active',
        'search' => null, // an Arabic name from the viewer's own first page
        'global search' => '/api/v1/search?q=fatimah',
    ];

    $report = [];

    foreach ($viewers as $role => $user) {
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/employees?per_page=25')->assertOk(); // warm caches

        $next = null;
        $arabicName = null;

        foreach ($endpoints as $label => $url) {
            $url ??= $label === 'search' ? ($arabicName === null ? null : '/api/v1/employees?per_page=25&search='.rawurlencode($arabicName)) : $next;

            if ($url === null) {
                continue;
            }

            $queries = 0;
            DB::listen(function () use (&$queries): void {
                $queries++;
            });

            $started = hrtime(true);
            $response = $this->getJson($url)->assertOk();
            $ms = (hrtime(true) - $started) / 1e6;

            DB::flushQueryLog();
            resolve('events')->forget(QueryExecuted::class);

            if ($label === 'list') {
                $next = is_string($response->json('meta.next_cursor')) ? '/api/v1/employees?per_page=25&cursor='.$response->json('meta.next_cursor') : null;
                $arabicName = explode(' ', (string) $response->json('data.0.name.ar'))[0];
            }

            expect($response->json('data'))->not->toBeEmpty("{$role} {$label} returned nothing");
            $report[] = sprintf('%-11s %-13s %6.1f ms %3d queries %3d rows', $role, $label, $ms, $queries, count($response->json('data')));

            expect($ms)->toBeLessThan(300.0, "{$role} {$label} took {$ms} ms")
                ->and($queries)->toBeLessThanOrEqual(15, "{$role} {$label} ran {$queries} queries");
        }
    }

    fwrite(STDERR, "\n".implode("\n", $report)."\n");
});
