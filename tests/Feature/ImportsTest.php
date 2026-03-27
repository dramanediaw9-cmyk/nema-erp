<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Treasury\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_company_admin_can_import_customers_from_csv(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $file = UploadedFile::fake()->createWithContent('clients.csv', implode("\n", [
            'code;name;phone;email;city;nif;address;opening_balance;notes',
            'CLI-9001;Client Import Test;70001122;import-client@example.com;Bamako;;ACI 2000;5000;Import test',
        ]));

        $this->actingAs($user)
            ->withSession([
                'current_company_id' => $user->company_id,
                'current_branch_id' => $user->branch_id,
            ])
            ->post(route('imports.customers.store'), [
                'file' => $file,
            ])
            ->assertRedirect(route('imports.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('partners', [
            'company_id' => $user->company_id,
            'code' => 'CLI-9001',
            'name' => 'Client Import Test',
            'email' => 'import-client@example.com',
        ]);
    }

    public function test_company_admin_can_import_suppliers_from_csv(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $file = UploadedFile::fake()->createWithContent('fournisseurs.csv', implode("\n", [
            'code;name;phone;email;city;nif;address;opening_balance;notes',
            'FOU-9001;Fournisseur Import Test;76001122;import-fournisseur@example.com;Bamako;;Zone indus;8000;Import test',
        ]));

        $this->actingAs($user)
            ->withSession([
                'current_company_id' => $user->company_id,
                'current_branch_id' => $user->branch_id,
            ])
            ->post(route('imports.suppliers.store'), [
                'file' => $file,
            ])
            ->assertRedirect(route('imports.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('partners', [
            'company_id' => $user->company_id,
            'code' => 'FOU-9001',
            'name' => 'Fournisseur Import Test',
            'email' => 'import-fournisseur@example.com',
        ]);
    }

    public function test_company_admin_can_import_products_from_csv(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $file = UploadedFile::fake()->createWithContent('produits.csv', implode("\n", [
            'sku;name;category;unit;type;sale_price;purchase_price;min_stock;description',
            'PRD-9001;Produit Import Test;Categorie Import;sac;stockable;12500;10000;4;Import test',
        ]));

        $this->actingAs($user)
            ->withSession([
                'current_company_id' => $user->company_id,
                'current_branch_id' => $user->branch_id,
            ])
            ->post(route('imports.products.store'), [
                'file' => $file,
            ])
            ->assertRedirect(route('imports.index'))
            ->assertSessionHas('success');

        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-9001')->first();
        $this->assertNotNull($product);
        $this->assertSame('Produit Import Test', $product->name);
        $this->assertNotNull($product->category_id);
    }

    public function test_company_admin_can_import_opening_stock_from_csv(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $file = UploadedFile::fake()->createWithContent('stock-initial.csv', implode("\n", [
            'sku;quantity;unit_cost;notes',
            'PRD-0001;12;9500;Import stock test',
        ]));

        $this->actingAs($user)
            ->withSession([
                'current_company_id' => $user->company_id,
                'current_branch_id' => $user->branch_id,
            ])
            ->post(route('imports.opening-stock.store'), [
                'file' => $file,
            ])
            ->assertRedirect(route('imports.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('stock_movements', [
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'product_id' => $product->id,
            'movement_type' => 'opening',
            'reason' => 'Stock initial',
            'notes' => 'Import stock test',
        ]);
    }

    public function test_company_admin_can_import_historical_sales_from_csv(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $file = UploadedFile::fake()->createWithContent('ventes-historiques.csv', implode("\n", [
            'invoice_number;invoice_date;due_date;customer_code;sku;description;qty;unit_price;amount_paid;payment_date;cash_account;notes',
            'HIS-VTE-0001;2026-03-01;2026-03-10;C0001;PRD-0001;Historique eau;2;400;900;2026-03-02;Caisse principale;Import vente historique',
            'HIS-VTE-0001;2026-03-01;2026-03-10;C0001;PRD-0002;Historique sucre;1;700;900;2026-03-02;Caisse principale;Import vente historique',
        ]));

        $this->actingAs($user)
            ->withSession([
                'current_company_id' => $user->company_id,
                'current_branch_id' => $user->branch_id,
            ])
            ->post(route('imports.historical-sales.store'), [
                'file' => $file,
            ])
            ->assertRedirect(route('imports.index'))
            ->assertSessionHas('success');

        $invoice = SalesInvoice::query()->where('company_id', $user->company_id)->where('invoice_number', 'HIS-VTE-0001')->first();
        $this->assertNotNull($invoice);
        $this->assertSame('partial', $invoice->payment_status);
        $this->assertEqualsWithDelta(900, (float) $invoice->amount_paid, 0.001);
        $this->assertEqualsWithDelta(600, (float) $invoice->balance_due, 0.001);

        $this->assertDatabaseHas('stock_movements', [
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'product_id' => $product->id,
            'movement_type' => 'sale',
            'reference_type' => SalesInvoice::class,
            'reference_id' => $invoice->id,
        ]);

        $this->assertDatabaseHas('payments', [
            'company_id' => $user->company_id,
            'payment_type' => 'customer_receipt',
            'reference' => 'IMPORT-HIS-VTE-0001',
            'amount' => 900,
        ]);
    }

    public function test_company_admin_can_import_historical_purchases_from_csv(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $file = UploadedFile::fake()->createWithContent('achats-historiques.csv', implode("\n", [
            'bill_number;bill_date;due_date;supplier_code;sku;description;qty;unit_cost;amount_paid;payment_date;cash_account;notes',
            'HIS-ACH-0001;2026-03-01;2026-03-12;F0001;PRD-0001;Historique achat eau;5;250;800;2026-03-03;Banque BDM;Import achat historique',
            'HIS-ACH-0001;2026-03-01;2026-03-12;F0001;PRD-0002;Historique achat sucre;3;500;800;2026-03-03;Banque BDM;Import achat historique',
        ]));

        $this->actingAs($user)
            ->withSession([
                'current_company_id' => $user->company_id,
                'current_branch_id' => $user->branch_id,
            ])
            ->post(route('imports.historical-purchases.store'), [
                'file' => $file,
            ])
            ->assertRedirect(route('imports.index'))
            ->assertSessionHas('success');

        $bill = PurchaseBill::query()->where('company_id', $user->company_id)->where('bill_number', 'HIS-ACH-0001')->first();
        $this->assertNotNull($bill);
        $this->assertSame('partial', $bill->payment_status);
        $this->assertEqualsWithDelta(800, (float) $bill->amount_paid, 0.001);
        $this->assertEqualsWithDelta(1950, (float) $bill->balance_due, 0.001);

        $this->assertDatabaseHas('stock_movements', [
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'product_id' => $product->id,
            'movement_type' => 'purchase',
            'reference_type' => PurchaseBill::class,
            'reference_id' => $bill->id,
        ]);

        $payment = Payment::query()->where('company_id', $user->company_id)->where('reference', 'IMPORT-HIS-ACH-0001')->first();
        $this->assertNotNull($payment);
        $this->assertSame('supplier_payment', $payment->payment_type);
        $this->assertEqualsWithDelta(800, (float) $payment->amount, 0.001);
    }
}