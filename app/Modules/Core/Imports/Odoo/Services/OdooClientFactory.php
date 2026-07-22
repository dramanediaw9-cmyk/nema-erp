<?php

namespace App\Modules\Core\Imports\Odoo\Services;

use App\Modules\Core\Imports\Odoo\Contracts\OdooClient;
use App\Modules\Core\Imports\Odoo\Models\OdooConnection;

class OdooClientFactory
{
    public function make(OdooConnection $connection): OdooClient
    {
        return new OdooRpcClient($connection);
    }
}
