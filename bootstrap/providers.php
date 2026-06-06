<?php

use App\Providers\AppServiceProvider;
use App\Providers\AccountProfileServiceProvider;
use App\Providers\AccountSecurityServiceProvider;
use App\Providers\SaasRegistrationServiceProvider;

return [
    AppServiceProvider::class,
    AccountProfileServiceProvider::class,
    AccountSecurityServiceProvider::class,
    SaasRegistrationServiceProvider::class,
];
