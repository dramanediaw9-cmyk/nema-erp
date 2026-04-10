<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

require __DIR__.'/modules/core.php';
require __DIR__.'/modules/platform.php';
require __DIR__.'/modules/partners.php';
require __DIR__.'/modules/catalog.php';
require __DIR__.'/modules/inventory.php';
require __DIR__.'/modules/sales.php';
require __DIR__.'/modules/treasury.php';
require __DIR__.'/modules/expenses.php';
require __DIR__.'/modules/purchases.php';
require __DIR__.'/modules/accounting.php';
require __DIR__.'/modules/reporting.php';
require __DIR__.'/modules/imports.php';
require __DIR__.'/modules/collections.php';
require __DIR__.'/modules/budgets.php';
require __DIR__.'/modules/fixed-assets.php';
require __DIR__.'/modules/crm.php';
require __DIR__.'/modules/hr.php';
require __DIR__.'/modules/payroll.php';
require __DIR__.'/modules/projects.php';
require __DIR__.'/modules/manufacturing.php';
require __DIR__.'/modules/commerce.php';

require __DIR__.'/modules/pos.php';

