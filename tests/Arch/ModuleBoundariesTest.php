<?php

declare(strict_types=1);

use App\Domain\Access\Queries\ScopedQuery;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelData\Data;

/*
| Module rules from ADR-0001 / ADR-0002. These guard the architecture as the codebase grows.
*/

arch('no debugging leftovers')
    ->expect(['dd', 'dump', 'ddd', 'ray', 'var_dump', 'print_r', 'die', 'exit'])
    ->not->toBeUsed();

arch('php preset')->preset()->php();

arch('security preset')->preset()->security()->ignoring([
    'App\Support\Templates', // uses preg_replace_callback deliberately; no eval
]);

arch('domain code is HTTP agnostic')
    ->expect('App\Domain')
    ->not->toUse([
        'App\Http',
        Request::class,
        Illuminate\Support\Facades\Request::class,
        'Inertia',
    ]);

arch('the shared kernel does not depend on domains')
    ->expect('App\Support')
    ->not->toUse('App\Domain')
    ->ignoring([
        'App\Support\Actors', // Actor carries the authenticated User
    ]);

arch('controllers do not touch the database directly')
    ->expect('App\Http\Controllers')
    ->not->toUse([DB::class, Builder::class]);

arch('controllers are thin and suffixed')
    ->expect('App\Http\Controllers')
    ->toHaveSuffix('Controller');

/*
| Per-domain rules apply to every domain folder that exists.
*/
$domains = array_map(basename(...), glob(dirname(__DIR__, 2).'/app/Domain/*', GLOB_ONLYDIR) ?: []);

foreach ($domains as $domain) {
    $base = "App\\Domain\\{$domain}";

    if (is_dir(dirname(__DIR__, 2)."/app/Domain/{$domain}/Actions")) {
        arch("{$domain} actions are final single-purpose classes")
            ->expect("{$base}\\Actions")
            ->classes()
            ->toBeFinal()
            ->toHaveMethod('handle');
    }

    if (is_dir(dirname(__DIR__, 2)."/app/Domain/{$domain}/Data")) {
        arch("{$domain} data objects extend spatie/laravel-data")
            ->expect("{$base}\\Data")
            ->classes()
            ->toExtend(Data::class);
    }

    if (is_dir(dirname(__DIR__, 2)."/app/Domain/{$domain}/Queries")) {
        arch("{$domain} queries are scope-aware")
            ->expect("{$base}\\Queries")
            ->classes()
            ->toExtend(ScopedQuery::class)
            ->ignoring(ScopedQuery::class);
    }

    if (is_dir(dirname(__DIR__, 2)."/app/Domain/{$domain}/Policies")) {
        arch("{$domain} policies are suffixed")
            ->expect("{$base}\\Policies")
            ->toHaveSuffix('Policy');
    }
}
