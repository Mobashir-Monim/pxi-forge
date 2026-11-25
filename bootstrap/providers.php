<?php

use App\Domains\Authorization\AuthorizationServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    AuthorizationServiceProvider::class,
    FortifyServiceProvider::class,
];
