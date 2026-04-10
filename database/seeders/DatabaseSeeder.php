<?php

namespace Database\Seeders;

use Database\Seeders\Accounting\AccountingPeriodSeeder;
use Database\Seeders\Accounting\ChartOfAccountsSeeder;
use Database\Seeders\Accounting\DemoAccountingSeeder;
use Database\Seeders\Catalog\DemoCatalogSeeder;
use Database\Seeders\Catalog\PosTestCatalogSeeder;
use Database\Seeders\Core\DemoCompanySeeder;
use Database\Seeders\Core\DemoPartnerSeeder;
use Database\Seeders\Core\DemoRoleSeeder;
use Database\Seeders\Core\DemoUserSeeder;
use Database\Seeders\Core\PermissionSeeder;
use Database\Seeders\Expenses\DemoExpenseSeeder;
use Database\Seeders\Growth\DemoGrowthFoundationSeeder;
use Database\Seeders\Inventory\DemoStockSeeder;
use Database\Seeders\Inventory\PosTestStockSeeder;
use Database\Seeders\Purchases\DemoPurchaseSeeder;
use Database\Seeders\Sales\DemoSalesSeeder;
use Database\Seeders\Treasury\DemoCashAccountSeeder;
use Database\Seeders\Treasury\DemoSupplierPaymentSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            DemoCompanySeeder::class,
            ChartOfAccountsSeeder::class,
            AccountingPeriodSeeder::class,
            DemoRoleSeeder::class,
            DemoUserSeeder::class,
            DemoPartnerSeeder::class,
            DemoCatalogSeeder::class,
            PosTestCatalogSeeder::class,
            DemoCashAccountSeeder::class,
            DemoStockSeeder::class,
            PosTestStockSeeder::class,
            DemoPurchaseSeeder::class,
            DemoSupplierPaymentSeeder::class,
            DemoSalesSeeder::class,
            DemoExpenseSeeder::class,
            DemoGrowthFoundationSeeder::class,
            DemoAccountingSeeder::class,
        ]);
    }
}
