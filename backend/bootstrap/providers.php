<?php

use App\Modules\Desarrollo\Infrastructure\Providers\DesarrolloServiceProvider;
use App\Modules\Estructura\Infrastructure\Providers\EstructuraServiceProvider;
use App\Modules\Shared\Infrastructure\Providers\SharedServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    DesarrolloServiceProvider::class,
    EstructuraServiceProvider::class,
    SharedServiceProvider::class,
];
