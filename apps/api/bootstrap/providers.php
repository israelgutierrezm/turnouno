<?php

declare(strict_types=1);

use App\Modules\Tenancy\TenancyServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    TenancyServiceProvider::class,
];
