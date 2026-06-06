<?php

use App\Providers\AppServiceProvider;
use App\Providers\AccountSecurityServiceProvider;
use App\Providers\SaasRegistrationServiceProvider;

return [
    AppServiceProvider::class,
    AccountSecurityServiceProvider::class,
    SaasRegistrationServiceProvider::class,
];
