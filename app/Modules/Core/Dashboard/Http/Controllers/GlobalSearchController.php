<?php

namespace App\Modules\Core\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Partners\Models\Partner;
use App\Modules\Purchases\Models\GoodsReceipt;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesQuote;
use App\Modules\Treasury\Models\Payment;
use App\Support\CurrentWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class GlobalSearchController extends Controller
{
    public function __invoke(Request $request, CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $query = $request->string('q')->trim()->value();
        $user = $request->user();
        $branchScopeId = $user ? $user->resolvedBranchScope(null, $workspace->branchId()) : $workspace->branchId();

        $definitions = collect([
            [
                'title' => 'Clients',
                'permission' => 'customers.view',
                'index_url' => route('customers.index', ['search' => $query]),
                'resolver' => fn () => $this->searchCustomers($companyId, $query),
            ],
            [
                'title' => 'Fournisseurs',
                'permission' => 'suppliers.view',
                'index_url' => route('suppliers.index', ['search' => $query]),
                'resolver' => fn () => $this->searchSuppliers($companyId, $query),
            ],
            [
                'title' => 'Produits',
                'permission' => 'products.view',
                'index_url' => route('products.index', ['search' => $query]),
                'resolver' => fn () => $this->searchProducts($companyId, $query),
            ],
            [
                'title' => 'Devis',
                'permission' => 'quotes.view',
                'index_url' => route('quotes.index', ['search' => $query]),
                'resolver' => fn () => $this->searchQuotes($companyId, $branchScopeId, $query),
            ],
            [
                'title' => 'Commandes clients',
                'permission' => 'orders.view',
                'index_url' => route('orders.index', ['search' => $query]),
                'resolver' => fn () => $this->searchOrders($companyId, $branchScopeId, $query),
            ],
            [
                'title' => 'Ventes',
                'permission' => 'sales.view',
                'index_url' => route('sales.index', ['search' => $query]),
                'resolver' => fn () => $this->searchSales($companyId, $branchScopeId, $query),
            ],
            [
                'title' => 'Commandes fournisseurs',
                'permission' => 'purchase_orders.view',
                'index_url' => route('purchase-orders.index'),
                'resolver' => fn () => $this->searchPurchaseOrders($companyId, $branchScopeId, $query),
            ],
            [
                'title' => 'Receptions fournisseurs',
                'permission' => 'goods_receipts.view',
                'index_url' => route('goods-receipts.index'),
                'resolver' => fn () => $this->searchGoodsReceipts($companyId, $branchScopeId, $query),
            ],
            [
                'title' => 'Achats',
                'permission' => 'purchases.view',
                'index_url' => route('purchases.index', ['search' => $query]),
                'resolver' => fn () => $this->searchPurchases($companyId, $branchScopeId, $query),
            ],
            [
                'title' => 'Paiements',
                'permission' => 'payments.view',
                'index_url' => route('payments.index', ['search' => $query]),
                'resolver' => fn () => $this->searchPayments($companyId, $branchScopeId, $query),
            ],
        ])->filter(fn (array $definition) => $user?->hasPermission($definition['permission']));

        $groups = collect();

        if ($query !== '') {
            $groups = $definitions
                ->map(function (array $definition) {
                    $items = $definition['resolver']();

                    return [
                        'title' => $definition['title'],
                        'index_url' => $definition['index_url'],
                        'count' => $items->count(),
                        'items' => $items,
                    ];
                })
                ->filter(fn (array $group) => $group['count'] > 0)
                ->values();
        }

        return view('search.index', [
            'query' => $query,
            'groups' => $groups,
            'availableScopes' => $definitions->pluck('title')->values(),
            'totalResults' => $groups->sum('count'),
        ]);
    }

    private function searchCustomers(int $companyId, string $query): Collection
    {
        return Partner::query()
            ->customers()
            ->where('company_id', $companyId)
            ->where($this->partnerSearchConstraint($query))
            ->orderBy('name')
            ->limit(5)
            ->get()
            ->map(fn (Partner $customer) => [
                'badge' => 'Client',
                'title' => $customer->name,
                'subtitle' => $this->joinMeta([$customer->code, $customer->city]),
                'meta' => $this->joinMeta([$customer->phone, $customer->email]),
                'url' => route('customers.show', $customer),
            ]);
    }

    private function searchSuppliers(int $companyId, string $query): Collection
    {
        return Partner::query()
            ->suppliers()
            ->where('company_id', $companyId)
            ->where($this->partnerSearchConstraint($query))
            ->orderBy('name')
            ->limit(5)
            ->get()
            ->map(fn (Partner $supplier) => [
                'badge' => 'Fournisseur',
                'title' => $supplier->name,
                'subtitle' => $this->joinMeta([$supplier->code, $supplier->city]),
                'meta' => $this->joinMeta([$supplier->phone, $supplier->email]),
                'url' => route('suppliers.show', $supplier),
            ]);
    }

    private function searchProducts(int $companyId, string $query): Collection
    {
        $like = '%'.$query.'%';

        return Product::query()
            ->with('category')
            ->where('company_id', $companyId)
            ->where(function (Builder $builder) use ($like): void {
                $builder->where('sku', 'like', $like)
                    ->orWhere('barcode', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhereHas('category', function (Builder $categoryQuery) use ($like): void {
                        $categoryQuery->where('name', 'like', $like);
                    });
            })
            ->orderBy('name')
            ->limit(6)
            ->get()
            ->map(fn (Product $product) => [
                'badge' => 'Produit',
                'title' => $product->name,
                'subtitle' => $this->joinMeta([$product->sku, $product->category?->name]),
                'meta' => $this->joinMeta([$product->barcode, $product->type === 'service' ? 'Service' : 'Stockable']),
                'url' => route('products.show', $product),
            ]);
    }

    private function searchQuotes(int $companyId, ?int $branchId, string $query): Collection
    {
        $like = '%'.$query.'%';

        return SalesQuote::query()
            ->with(['customer', 'branch'])
            ->where('company_id', $companyId)
            ->when($branchId, fn (Builder $queryBuilder, int $selectedBranchId) => $queryBuilder->where('branch_id', $selectedBranchId))
            ->where(function (Builder $builder) use ($like, $query): void {
                $builder->where('quote_number', 'like', $like)
                    ->orWhere('notes', 'like', $like)
                    ->orWhereHas('customer', fn (Builder $customerQuery) => $customerQuery->where($this->partnerSearchConstraint($query)));
            })
            ->latest('quote_date')
            ->limit(5)
            ->get()
            ->map(fn (SalesQuote $quote) => [
                'badge' => 'Devis',
                'title' => $quote->quote_number,
                'subtitle' => $this->joinMeta([$quote->customer?->name, $quote->branch?->name]),
                'meta' => $this->joinMeta([$quote->quote_date?->format('d/m/Y'), $this->labelize($quote->status)]),
                'url' => route('quotes.show', $quote),
            ]);
    }

    private function searchOrders(int $companyId, ?int $branchId, string $query): Collection
    {
        $like = '%'.$query.'%';

        return SalesOrder::query()
            ->with(['customer', 'branch'])
            ->where('company_id', $companyId)
            ->when($branchId, fn (Builder $queryBuilder, int $selectedBranchId) => $queryBuilder->where('branch_id', $selectedBranchId))
            ->where(function (Builder $builder) use ($like, $query): void {
                $builder->where('order_number', 'like', $like)
                    ->orWhere('notes', 'like', $like)
                    ->orWhereHas('customer', fn (Builder $customerQuery) => $customerQuery->where($this->partnerSearchConstraint($query)));
            })
            ->latest('order_date')
            ->limit(5)
            ->get()
            ->map(fn (SalesOrder $order) => [
                'badge' => 'Commande client',
                'title' => $order->order_number,
                'subtitle' => $this->joinMeta([$order->customer?->name, $order->branch?->name]),
                'meta' => $this->joinMeta([$order->order_date?->format('d/m/Y'), $this->labelize($order->status)]),
                'url' => route('orders.show', $order),
            ]);
    }

    private function searchSales(int $companyId, ?int $branchId, string $query): Collection
    {
        $like = '%'.$query.'%';

        return SalesInvoice::query()
            ->with(['customer', 'branch'])
            ->where('company_id', $companyId)
            ->when($branchId, fn (Builder $queryBuilder, int $selectedBranchId) => $queryBuilder->where('branch_id', $selectedBranchId))
            ->where(function (Builder $builder) use ($like, $query): void {
                $builder->where('invoice_number', 'like', $like)
                    ->orWhere('notes', 'like', $like)
                    ->orWhereHas('customer', fn (Builder $customerQuery) => $customerQuery->where($this->partnerSearchConstraint($query)));
            })
            ->latest('invoice_date')
            ->limit(5)
            ->get()
            ->map(fn (SalesInvoice $sale) => [
                'badge' => 'Vente',
                'title' => $sale->invoice_number,
                'subtitle' => $this->joinMeta([$sale->customer?->name, $sale->branch?->name]),
                'meta' => $this->joinMeta([$sale->invoice_date?->format('d/m/Y'), $this->labelize($sale->status), $this->labelize($sale->payment_status)]),
                'url' => route('sales.show', $sale),
            ]);
    }

    private function searchPurchaseOrders(int $companyId, ?int $branchId, string $query): Collection
    {
        $like = '%'.$query.'%';

        return PurchaseOrder::query()
            ->with(['supplier', 'warehouse'])
            ->where('company_id', $companyId)
            ->when($branchId, fn (Builder $queryBuilder, int $selectedBranchId) => $queryBuilder->where('branch_id', $selectedBranchId))
            ->where(function (Builder $builder) use ($like, $query): void {
                $builder->where('order_number', 'like', $like)
                    ->orWhere('notes', 'like', $like)
                    ->orWhereHas('supplier', fn (Builder $supplierQuery) => $supplierQuery->where($this->partnerSearchConstraint($query)));
            })
            ->latest('order_date')
            ->limit(5)
            ->get()
            ->map(fn (PurchaseOrder $order) => [
                'badge' => 'Commande fournisseur',
                'title' => $order->order_number,
                'subtitle' => $this->joinMeta([$order->supplier?->name, $order->warehouse?->name]),
                'meta' => $this->joinMeta([$order->order_date?->format('d/m/Y'), $this->labelize($order->status)]),
                'url' => route('purchase-orders.show', $order),
            ]);
    }

    private function searchGoodsReceipts(int $companyId, ?int $branchId, string $query): Collection
    {
        $like = '%'.$query.'%';

        return GoodsReceipt::query()
            ->with(['supplier', 'warehouse', 'purchaseOrder'])
            ->where('company_id', $companyId)
            ->when($branchId, fn (Builder $queryBuilder, int $selectedBranchId) => $queryBuilder->where('branch_id', $selectedBranchId))
            ->where(function (Builder $builder) use ($like, $query): void {
                $builder->where('receipt_number', 'like', $like)
                    ->orWhere('notes', 'like', $like)
                    ->orWhereHas('purchaseOrder', function (Builder $orderQuery) use ($like): void {
                        $orderQuery->where('order_number', 'like', $like);
                    })
                    ->orWhereHas('supplier', fn (Builder $supplierQuery) => $supplierQuery->where($this->partnerSearchConstraint($query)));
            })
            ->latest('receipt_date')
            ->limit(5)
            ->get()
            ->map(fn (GoodsReceipt $receipt) => [
                'badge' => 'Reception',
                'title' => $receipt->receipt_number,
                'subtitle' => $this->joinMeta([$receipt->supplier?->name, $receipt->warehouse?->name]),
                'meta' => $this->joinMeta([$receipt->receipt_date?->format('d/m/Y'), $receipt->purchaseOrder?->order_number]),
                'url' => route('goods-receipts.show', $receipt),
            ]);
    }

    private function searchPurchases(int $companyId, ?int $branchId, string $query): Collection
    {
        $like = '%'.$query.'%';

        return PurchaseBill::query()
            ->with(['supplier', 'branch', 'purchaseOrder', 'goodsReceipt'])
            ->where('company_id', $companyId)
            ->when($branchId, fn (Builder $queryBuilder, int $selectedBranchId) => $queryBuilder->where('branch_id', $selectedBranchId))
            ->where(function (Builder $builder) use ($like, $query): void {
                $builder->where('bill_number', 'like', $like)
                    ->orWhere('notes', 'like', $like)
                    ->orWhereHas('purchaseOrder', function (Builder $orderQuery) use ($like): void {
                        $orderQuery->where('order_number', 'like', $like);
                    })
                    ->orWhereHas('goodsReceipt', function (Builder $receiptQuery) use ($like): void {
                        $receiptQuery->where('receipt_number', 'like', $like);
                    })
                    ->orWhereHas('supplier', fn (Builder $supplierQuery) => $supplierQuery->where($this->partnerSearchConstraint($query)));
            })
            ->latest('bill_date')
            ->limit(5)
            ->get()
            ->map(fn (PurchaseBill $bill) => [
                'badge' => 'Achat',
                'title' => $bill->bill_number,
                'subtitle' => $this->joinMeta([$bill->supplier?->name, $bill->branch?->name]),
                'meta' => $this->joinMeta([$bill->bill_date?->format('d/m/Y'), $this->labelize($bill->status), $this->labelize($bill->payment_status)]),
                'url' => route('purchases.show', $bill),
            ]);
    }

    private function searchPayments(int $companyId, ?int $branchId, string $query): Collection
    {
        $like = '%'.$query.'%';

        return Payment::query()
            ->with(['partner', 'cashAccount', 'branch'])
            ->where('company_id', $companyId)
            ->when($branchId, fn (Builder $queryBuilder, int $selectedBranchId) => $queryBuilder->where('branch_id', $selectedBranchId))
            ->where(function (Builder $builder) use ($like, $query): void {
                $builder->where('payment_number', 'like', $like)
                    ->orWhere('reference', 'like', $like)
                    ->orWhere('notes', 'like', $like)
                    ->orWhereHas('partner', fn (Builder $partnerQuery) => $partnerQuery->where($this->partnerSearchConstraint($query)))
                    ->orWhereHas('cashAccount', fn (Builder $cashAccountQuery) => $cashAccountQuery->where($this->cashAccountSearchConstraint($query)));
            })
            ->latest('payment_date')
            ->limit(5)
            ->get()
            ->map(fn (Payment $payment) => [
                'badge' => 'Paiement',
                'title' => $payment->payment_number,
                'subtitle' => $this->joinMeta([$payment->partner?->name, $payment->cashAccount?->name]),
                'meta' => $this->joinMeta([$payment->payment_date?->format('d/m/Y'), $this->labelize($payment->payment_type), $this->money($payment->amount)]),
                'url' => route('payments.show', $payment),
            ]);
    }

    private function partnerSearchConstraint(string $query): \Closure
    {
        $like = '%'.$query.'%';

        return function (Builder $builder) use ($like): void {
            $builder->where('code', 'like', $like)
                ->orWhere('name', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('city', 'like', $like)
                ->orWhere('nif', 'like', $like);
        };
    }

    private function cashAccountSearchConstraint(string $query): \Closure
    {
        $like = '%'.$query.'%';

        return function (Builder $builder) use ($like): void {
            $builder->where('name', 'like', $like)
                ->orWhere('account_number', 'like', $like)
                ->orWhere('type', 'like', $like);
        };
    }

    private function joinMeta(array $parts): string
    {
        return collect($parts)
            ->filter(fn ($part) => filled($part))
            ->map(fn ($part) => (string) $part)
            ->implode(' · ');
    }

    private function labelize(?string $value): string
    {
        if (! filled($value)) {
            return '';
        }

        return (string) str($value)->replace('_', ' ')->title();
    }

    private function money(float $amount): string
    {
        return number_format($amount, 0, ',', ' ').' XOF';
    }
}



