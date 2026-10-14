<?php

use App\Providers\AccessServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FormsServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\IntegrationsServiceProvider;
use App\Providers\NotificationsServiceProvider;
use App\Providers\PeopleServiceProvider;
use App\Providers\PoliciesServiceProvider;
use App\Providers\RequestsServiceProvider;
use App\Providers\TypeScriptTransformerServiceProvider;
use App\Providers\WorkflowsServiceProvider;

return [
    AppServiceProvider::class,
    AccessServiceProvider::class,
    PeopleServiceProvider::class,
    PoliciesServiceProvider::class,
    FormsServiceProvider::class,
    WorkflowsServiceProvider::class,
    RequestsServiceProvider::class,
    NotificationsServiceProvider::class,
    IntegrationsServiceProvider::class,
    FortifyServiceProvider::class,
    HorizonServiceProvider::class,
    TypeScriptTransformerServiceProvider::class,
];
