<?php

use App\Providers\AccessServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FormsServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\HiringServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\IntegrationsServiceProvider;
use App\Providers\LeaveServiceProvider;
use App\Providers\NotificationsServiceProvider;
use App\Providers\PeopleServiceProvider;
use App\Providers\PoliciesServiceProvider;
use App\Providers\ReportsServiceProvider;
use App\Providers\RequestsServiceProvider;
use App\Providers\TimeServiceProvider;
use App\Providers\TrainingServiceProvider;
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
    LeaveServiceProvider::class,
    TimeServiceProvider::class,
    HiringServiceProvider::class,
    TrainingServiceProvider::class,
    ReportsServiceProvider::class,
    NotificationsServiceProvider::class,
    IntegrationsServiceProvider::class,
    FortifyServiceProvider::class,
    HorizonServiceProvider::class,
    TypeScriptTransformerServiceProvider::class,
];
