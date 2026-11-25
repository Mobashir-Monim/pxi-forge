<?php

use App\Domains\Authorization\AuthorizationServiceProvider;
use App\Domains\Generation\GenerationServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\ServiceBindingProvider;

return [
    AppServiceProvider::class,
    AuthorizationServiceProvider::class,
    FortifyServiceProvider::class,
    GenerationServiceProvider::class,
    ServiceBindingProvider::class,
];
