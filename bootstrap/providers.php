<?php

use App\Providers\AccessServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\PeopleServiceProvider;
use App\Providers\TypeScriptTransformerServiceProvider;

return [
    AppServiceProvider::class,
    AccessServiceProvider::class,
    PeopleServiceProvider::class,
    FortifyServiceProvider::class,
    HorizonServiceProvider::class,
    TypeScriptTransformerServiceProvider::class,
];
