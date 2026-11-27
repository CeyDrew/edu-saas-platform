<?php

use App\Domain\Ai\AiServiceProvider;
use App\Domain\Catalog\CatalogServiceProvider;
use App\Domain\Sales\SalesServiceProvider;
use App\Domain\Tenancy\TenancyServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;

return [
    TenancyServiceProvider::class,
    CatalogServiceProvider::class,
    SalesServiceProvider::class,
    AiServiceProvider::class,
    AppServiceProvider::class,
    HorizonServiceProvider::class,
];
