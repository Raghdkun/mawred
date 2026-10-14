<?php

declare(strict_types=1);

use App\Domain\Access\Actions\SeedDefaultRoles;
use App\Domain\Access\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\BrowserTestCase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Volume and speed checks run like a real worker: no wrapping transaction.
pest()->extend(TestCase::class)
    ->use(DatabaseTruncation::class)
    ->in('Performance');

pest()->extend(BrowserTestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Browser');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', fn () => $this->toBe(1));

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Seed the permission catalog and the default roles (production seeder subset).
 */
function seedAccess(): void
{
    resolve(SeedDefaultRoles::class)->handle();
}

/**
 * A user holding the given seeded or custom roles.
 */
function userWithRoles(string ...$roles): User
{
    $user = User::factory()->create();
    $user->syncRoles($roles);

    return $user->fresh() ?? $user;
}

/**
 * A one-page MFS v1 schema from a list of fields.
 *
 * @param  list<array<string, mixed>>  $fields
 * @return array<string, mixed>
 */
function mfs(array $fields): array
{
    return ['format' => 'mawred.form/v1', 'pages' => [['key' => 'main', 'title' => ['en' => 'Main'], 'sections' => [['key' => 'details', 'title' => null, 'fields' => $fields]]]]];
}

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function field(string $key, string $type, array $extra = []): array
{
    return ['key' => $key, 'type' => $type, 'label' => ['en' => ucfirst(str_replace('_', ' ', $key)), 'ar' => $key], ...$extra];
}
