<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Budgets\Models\Budget;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductCategory;
use App\Modules\Core\Access\Models\Role;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Collaboration\Models\Attachment;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Imports\Odoo\Models\OdooProductImportRun;
use App\Modules\Crm\Models\Opportunity;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Expenses\Models\ExpenseCategory;
use App\Modules\FixedAssets\Models\FixedAsset;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Partners\Models\Partner;
use App\Modules\Pos\Models\PosPreparationDisplay;
use App\Modules\Pos\Models\PosPreparationTicket;
use App\Modules\Pos\Models\PosSession;
use App\Modules\Purchases\Models\GoodsReceipt;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Purchases\Models\PurchaseCreditNote;
use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Purchases\Models\PurchaseRequest;
use App\Modules\Sales\Models\DeliveryNote;
use App\Modules\Sales\Models\SalesCreditNote;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesQuote;
use App\Modules\Treasury\Models\CashAccount;
use App\Modules\Treasury\Models\Payment;
use App\Modules\Treasury\Models\TreasuryReconciliation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ParameterizedAuthenticatedRouteSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_seeded_parameterized_get_routes_avoid_missing_server_error_and_empty_html(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->actingAs($manager)->withSession([
            'current_tenant_id' => $manager->tenant_id,
            'current_company_id' => $manager->company_id,
            'current_branch_id' => $manager->branch_id,
        ]);

        $routes = collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $route): bool => in_array('GET', $route->methods(), true))
            ->filter(fn (Route $route): bool => str_contains($route->uri(), '{'))
            ->filter(fn (Route $route): bool => in_array('auth', $route->gatherMiddleware(), true))
            ->sortBy(fn (Route $route): string => $route->uri())
            ->values();

        $this->assertSame(60, $routes->count(), 'L’inventaire des routes GET paramétrées a changé : mettez à jour leur couverture.');

        $failures = [];
        $uncovered = [];
        $covered = 0;
        $delegated = 0;
        $dedicatedTestSource = collect(File::allFiles(base_path('tests/Feature')))
            ->reject(fn (\SplFileInfo $file): bool => $file->getFilename() === basename(__FILE__))
            ->map(fn (\SplFileInfo $file): string => File::get($file->getPathname()))
            ->implode("\n");

        foreach ($routes as $route) {
            $parameter = $route->parameterNames()[0] ?? null;
            $value = $parameter ? $this->routeValue($parameter, (string) $route->getName(), $manager) : null;

            if ($value === null) {
                $routeName = (string) $route->getName();

                if ($routeName !== '' && str_contains($dedicatedTestSource, $routeName)) {
                    $delegated++;
                } else {
                    $uncovered[] = ($routeName ?: 'sans nom').' ['.$route->uri().']';
                }

                continue;
            }

            $url = preg_replace('/\{[^}]+\}/', rawurlencode((string) $value), $route->uri(), 1);
            $response = $this->get('/'.$url);
            $status = $response->getStatusCode();
            $label = ($route->getName() ?: 'sans nom').' ['.$url.']';
            $covered++;

            if ($status === 404 || $status >= 500) {
                $failures[] = $label.' retourne HTTP '.$status;

                continue;
            }

            $contentType = (string) $response->headers->get('content-type', '');
            $content = $response->getContent();

            if (
                $status === 200
                && str_contains(strtolower($contentType), 'text/html')
                && is_string($content)
                && trim($content) === ''
            ) {
                $failures[] = $label.' retourne une page HTML vide';
            }
        }

        $this->assertGreaterThanOrEqual(
            30,
            $covered,
            "Trop de routes paramétrées sans données de test :\n".implode(PHP_EOL, $uncovered),
        );
        $this->assertSame([], $uncovered, "Routes paramétrées sans smoke ni test métier :\n".implode(PHP_EOL, $uncovered));
        $this->assertSame(60, $covered + $delegated, 'Chaque route paramétrée doit être ouverte directement ou couverte par un flux métier dédié.');
        $this->assertSame([], $failures, implode(PHP_EOL, $failures));
    }

    private function routeValue(string $parameter, string $routeName, User $manager): string|int|null
    {
        if ($parameter === 'type') {
            return 'products';
        }

        if ($parameter === 'customer') {
            return Partner::query()
                ->customers()
                ->where('company_id', $manager->company_id)
                ->value((new Partner)->getRouteKeyName());
        }

        if ($parameter === 'supplier') {
            return Partner::query()
                ->suppliers()
                ->where('company_id', $manager->company_id)
                ->value((new Partner)->getRouteKeyName());
        }

        $modelClass = match ($parameter) {
            'attachment' => Attachment::class,
            'branch' => Branch::class,
            'budget' => Budget::class,
            'cashAccount' => CashAccount::class,
            'category' => ProductCategory::class,
            'company' => Company::class,
            'creditNote' => str_starts_with($routeName, 'purchase-credit-notes.')
                ? PurchaseCreditNote::class
                : SalesCreditNote::class,
            'deliveryNote' => DeliveryNote::class,
            'display' => PosPreparationDisplay::class,
            'expense' => Expense::class,
            'expenseCategory' => ExpenseCategory::class,
            'fixedAsset' => FixedAsset::class,
            'goodsReceipt' => GoodsReceipt::class,
            'journalEntry' => JournalEntry::class,
            'opportunity' => Opportunity::class,
            'order' => SalesOrder::class,
            'payment' => Payment::class,
            'product' => Product::class,
            'purchase' => PurchaseBill::class,
            'purchaseOrder' => PurchaseOrder::class,
            'purchaseRequest' => PurchaseRequest::class,
            'quote' => SalesQuote::class,
            'role' => Role::class,
            'run' => OdooProductImportRun::class,
            'sale' => SalesInvoice::class,
            'session' => PosSession::class,
            'stockCount' => StockCount::class,
            'ticket' => PosPreparationTicket::class,
            'transfer' => StockTransfer::class,
            'treasuryReconciliation' => TreasuryReconciliation::class,
            'user' => User::class,
            default => null,
        };

        if (! $modelClass) {
            return null;
        }

        /** @var Model $model */
        $model = new $modelClass;
        $query = $modelClass::query();

        if (Schema::hasColumn($model->getTable(), 'company_id')) {
            $query->where('company_id', $manager->company_id);
        }

        if ($modelClass === Role::class) {
            $query->whereNotNull('company_id');
        }

        return $query->value($model->getRouteKeyName());
    }
}
