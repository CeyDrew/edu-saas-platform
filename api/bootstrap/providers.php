<?php

use App\Domain\Catalog\CatalogServiceProvider;
use App\Domain\Tenancy\TenancyServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;

return [
    TenancyServiceProvider::class,
    CatalogServiceProvider::class,
    AppServiceProvider::class,
    HorizonServiceProvider::class,
];
