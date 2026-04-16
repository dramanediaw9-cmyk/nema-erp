<?php

namespace App\Modules\Pos\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductAttribute;
use App\Modules\Catalog\Models\ProductCategory;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\PriceList;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Partners\Models\Partner;
use App\Modules\Pos\Models\PosComboChoice;
use App\Modules\Pos\Models\PosDraft;
use App\Modules\Pos\Models\PosLoyaltyProgram;
use App\Modules\Pos\Models\PosMenuCategory;
use App\Modules\Pos\Models\PosNoteTemplate;
use App\Modules\Pos\Models\PosPaymentMethod;
use App\Modules\Pos\Models\PosPreparationDisplay;
use App\Modules\Pos\Models\PosPreparationPrinter;
use App\Modules\Pos\Models\PosProductTag;
use App\Modules\Pos\Models\PosProfile;
use App\Modules\Pos\Models\PosReturn;
use App\Modules\Pos\Models\PosSession;
use App\Modules\Pos\Models\PosStoredValueCard;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Treasury\Models\CashAccount;
use App\Modules\Treasury\Models\Payment;
use App\Support\PaymentMethodCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PosBackofficeService
{
    public function __construct(
        private readonly PosService $posService,
    ) {
    }

    public function moduleMenu(): array
    {
        return [
            'main' => [
                ['label' => 'Tableau de bord', 'url' => route('pos.index')],
                ['label' => 'Commandes', 'url' => route('pos.orders.index')],
                ['label' => 'Sessions', 'url' => route('pos.sessions.index')],
                ['label' => 'Paiements', 'url' => route('pos.payments.index')],
                ['label' => 'Clients', 'url' => route('pos.customers.index')],
                ['label' => 'Produits', 'url' => route('pos.products.index')],
                ['label' => 'Preparation', 'url' => route('pos.preparation.index')],
                ['label' => 'Analyse', 'url' => route('pos.analytics.index')],
                ['label' => 'Configuration', 'url' => route('pos.settings.index')],
            ],
            'pricing' => [
                ['label' => 'Listes de prix', 'url' => route('pos.pricing.index', ['focus' => 'pricelists'])],
                ['label' => 'Remise & Fidelite', 'url' => route('pos.pricing.index', ['focus' => 'loyalty'])],
                ['label' => 'Cartes-cadeaux & e-wallet', 'url' => route('pos.pricing.index', ['focus' => 'stored-value'])],
            ],
            'settings' => [
                ['label' => 'Parametres', 'url' => route('pos.settings.index', ['focus' => 'profiles'])],
                ['label' => 'Modes de paiement', 'url' => route('pos.settings.index', ['focus' => 'payment-methods'])],
                ['label' => 'Preraglages', 'url' => route('pos.settings.index', ['focus' => 'profiles'])],
                ['label' => 'Pieces/billets', 'url' => route('pos.settings.index', ['focus' => 'denominations'])],
                ['label' => 'Point de vente', 'url' => route('pos.settings.index', ['focus' => 'profiles'])],
                ['label' => 'Modeles de notes', 'url' => route('pos.settings.index', ['focus' => 'note-templates'])],
                ['label' => 'Categories de produits du PdV', 'url' => route('pos.products.index', ['focus' => 'menu-categories'])],
                ['label' => 'Attributs', 'url' => route('pos.products.index', ['focus' => 'attributes'])],
                ['label' => 'Etiquettes de produit', 'url' => route('pos.products.index', ['focus' => 'tags'])],
                ['label' => 'Imprimantes de preparation', 'url' => route('pos.settings.index', ['focus' => 'printers'])],
                ['label' => 'Preparation Display', 'url' => route('pos.settings.index', ['focus' => 'displays'])],
                ['label' => 'Choix de combo', 'url' => route('pos.products.index', ['focus' => 'combos'])],
            ],
        ];
    }

    public function dashboard(int $companyId, int $branchId, ?int $userId = null): array
    {
        $currentSession = $userId ? $this->posService->currentOpenSession($companyId, $branchId, $userId) : null;

        return [
            'current_session' => $currentSession,
            'stats' => [
                'open_sessions' => (int) PosSession::query()->where('company_id', $companyId)->where('branch_id', $branchId)->where('status', 'open')->count(),
                'orders_today' => (int) SalesInvoice::query()->where('company_id', $companyId)->where('branch_id', $branchId)->where('sale_channel', 'pos')->whereDate('invoice_date', now()->toDateString())->count(),
                'payments_today' => (float) Payment::query()->where('company_id', $companyId)->where('branch_id', $branchId)->whereHas('posSession')->whereDate('payment_date', now()->toDateString())->sum('amount'),
                'price_lists' => (int) PriceList::query()->where('company_id', $companyId)->where('is_active', true)->count(),
                'loyalty_programs' => (int) PosLoyaltyProgram::query()->where('company_id', $companyId)->where('is_active', true)->count(),
                'stored_value_cards' => (int) PosStoredValueCard::query()->where('company_id', $companyId)->where('status', 'active')->count(),
                'prep_printers' => (int) PosPreparationPrinter::query()->where('company_id', $companyId)->where('is_active', true)->count(),
                'prep_displays' => (int) PosPreparationDisplay::query()->where('company_id', $companyId)->where('is_active', true)->count(),
            ],
        ];
    }

    public function orders(int $companyId, int $branchId): array
    {
        return [
            'summary' => [
                'orders' => (int) SalesInvoice::query()->where('company_id', $companyId)->where('branch_id', $branchId)->where('sale_channel', 'pos')->count(),
                'drafts' => (int) PosDraft::query()->where('company_id', $companyId)->where('branch_id', $branchId)->count(),
                'returns' => (int) PosReturn::query()->where('company_id', $companyId)->where('branch_id', $branchId)->count(),
                'paid' => (int) SalesInvoice::query()->where('company_id', $companyId)->where('branch_id', $branchId)->where('sale_channel', 'pos')->where('payment_status', 'paid')->count(),
            ],
            'invoices' => SalesInvoice::query()
                ->with(['customer', 'creator', 'posSession'])
                ->where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->where('sale_channel', 'pos')
                ->latest('invoice_date')
                ->latest('id')
                ->limit(16)
                ->get(),
            'drafts' => PosDraft::query()
                ->with(['customer', 'creator', 'updater'])
                ->where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->latest('last_activity_at')
                ->latest('id')
                ->limit(12)
                ->get(),
            'returns' => PosReturn::query()
                ->with(['invoice.customer', 'exchangeInvoice'])
                ->where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->latest('return_date')
                ->latest('id')
                ->limit(12)
                ->get(),
        ];
    }

    public function sessions(int $companyId, int $branchId): array
    {
        $sessions = PosSession::query()
            ->with(['cashAccount', 'warehouse', 'opener', 'closer'])
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->latest('opened_at')
            ->limit(18)
            ->get();

        return [
            'summary' => [
                'open_sessions' => (int) $sessions->where('status', 'open')->count(),
                'closed_sessions' => (int) $sessions->where('status', 'closed')->count(),
                'expected_cash' => round((float) $sessions->sum('expected_amount'), 2),
                'variance_total' => round((float) $sessions->sum('variance_amount'), 2),
            ],
            'sessions' => $sessions,
        ];
    }

    public function payments(int $companyId, int $branchId): array
    {
        $payments = Payment::query()
            ->with(['cashAccount', 'partner', 'posSession'])
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->whereHas('posSession')
            ->latest('payment_date')
            ->latest('id')
            ->limit(20)
            ->get();

        $totalsByMethod = collect(PaymentMethodCatalog::options())->mapWithKeys(function (string $label, string $method) use ($payments): array {
            return [$method => [
                'label' => $label,
                'amount' => round((float) $payments->where('method', $method)->sum('amount'), 2),
                'count' => (int) $payments->where('method', $method)->count(),
            ]];
        });

        return [
            'summary' => [
                'payments' => (int) $payments->count(),
                'cash_total' => round((float) $payments->where('method', 'cash')->sum('amount'), 2),
                'mobile_total' => round((float) $payments->whereIn('method', ['wave', 'orange_money', 'moov_money', 'mobile_money'])->sum('amount'), 2),
                'configured_methods' => (int) PosPaymentMethod::query()->where('company_id', $companyId)->where('is_active', true)->count(),
            ],
            'payments' => $payments,
            'totals_by_method' => $totalsByMethod,
            'configured_methods' => PosPaymentMethod::query()
                ->with(['cashAccount', 'branch'])
                ->where('company_id', $companyId)
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get(),
        ];
    }

    public function customers(int $companyId, int $branchId): array
    {
        $topCustomers = DB::table('sales_invoices')
            ->join('partners', 'partners.id', '=', 'sales_invoices.customer_id')
            ->where('sales_invoices.company_id', $companyId)
            ->where('sales_invoices.branch_id', $branchId)
            ->where('sales_invoices.sale_channel', 'pos')
            ->selectRaw('partners.id, partners.name, partners.phone, COUNT(*) as tickets, SUM(sales_invoices.total) as amount')
            ->groupBy('partners.id', 'partners.name', 'partners.phone')
            ->orderByDesc('amount')
            ->limit(12)
            ->get();

        return [
            'summary' => [
                'customers' => (int) Partner::query()->customers()->where('company_id', $companyId)->count(),
                'active_customers' => (int) Partner::query()->customers()->where('company_id', $companyId)->where('is_active', true)->count(),
                'pos_customers' => (int) $topCustomers->count(),
                'wallets' => (int) PosStoredValueCard::query()->where('company_id', $companyId)->where('card_type', 'e_wallet')->where('status', 'active')->count(),
            ],
            'top_customers' => $topCustomers,
            'recent_customers' => Partner::query()->customers()->where('company_id', $companyId)->latest('id')->limit(10)->get(),
        ];
    }

    public function products(int $companyId): array
    {
        $products = Product::query()
            ->with(['category', 'parent', 'attributeValues.attribute'])
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get();

        return [
            'summary' => [
                'products' => (int) $products->count(),
                'variants' => (int) $products->where('is_variant', true)->count(),
                'categories' => (int) ProductCategory::query()->where('company_id', $companyId)->count(),
                'attributes' => (int) ProductAttribute::query()->where('company_id', $companyId)->count(),
                'combos' => (int) PosComboChoice::query()->where('company_id', $companyId)->where('is_active', true)->count(),
                'menu_categories' => (int) PosMenuCategory::query()->where('company_id', $companyId)->where('is_active', true)->count(),
                'tags' => (int) PosProductTag::query()->where('company_id', $companyId)->where('is_active', true)->count(),
            ],
            'products' => $products->take(18),
            'categories' => ProductCategory::query()->withCount('products')->where('company_id', $companyId)->orderBy('name')->get(),
            'attributes' => ProductAttribute::query()->withCount('values')->where('company_id', $companyId)->orderBy('name')->get(),
            'combos' => PosComboChoice::query()->with(['branch', 'parentProduct'])->where('company_id', $companyId)->latest('id')->get(),
            'menu_categories' => PosMenuCategory::query()->where('company_id', $companyId)->orderBy('sort_order')->orderBy('name')->get(),
            'product_tags' => PosProductTag::query()->where('company_id', $companyId)->orderBy('name')->get(),
        ];
    }

    public function pricing(int $companyId): array
    {
        $storedValueCards = PosStoredValueCard::query()
            ->with(['partner', 'branch'])
            ->where('company_id', $companyId)
            ->latest('id')
            ->limit(18)
            ->get();

        return [
            'summary' => [
                'price_lists' => (int) PriceList::query()->where('company_id', $companyId)->where('is_active', true)->count(),
                'loyalty_programs' => (int) PosLoyaltyProgram::query()->where('company_id', $companyId)->where('is_active', true)->count(),
                'gift_cards' => (int) PosStoredValueCard::query()->where('company_id', $companyId)->where('card_type', 'gift_card')->where('status', 'active')->count(),
                'e_wallets' => (int) PosStoredValueCard::query()->where('company_id', $companyId)->where('card_type', 'e_wallet')->where('status', 'active')->count(),
                'stored_value_balance' => round((float) $storedValueCards->sum('balance'), 2),
            ],
            'price_lists' => PriceList::query()->with('items.product')->where('company_id', $companyId)->orderByDesc('is_default')->orderBy('name')->get(),
            'loyalty_programs' => PosLoyaltyProgram::query()->with('branch')->where('company_id', $companyId)->orderBy('name')->get(),
            'stored_value_cards' => $storedValueCards,
        ];
    }

    public function analytics(int $companyId, int $branchId): array
    {
        $report = $this->posService->dailyReport($companyId, $branchId, [
            'date' => now()->toDateString(),
            'warehouse_id' => null,
            'cash_account_id' => null,
        ]);

        $prepPrinters = PosPreparationPrinter::query()->where('company_id', $companyId)->where('is_active', true)->get();
        $prepDisplays = PosPreparationDisplay::query()->where('company_id', $companyId)->where('is_active', true)->get();
        $prepTargets = $prepPrinters->pluck('prep_time_target_minutes')->merge($prepDisplays->pluck('prep_time_target_minutes'))->filter(fn ($value) => (int) $value > 0);

        return [
            'report' => $report,
            'session_report' => PosSession::query()
                ->where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->latest('opened_at')
                ->limit(10)
                ->get(),
            'prep' => [
                'printers' => $prepPrinters,
                'displays' => $prepDisplays,
                'average_target_minutes' => $prepTargets->isEmpty() ? null : round((float) $prepTargets->avg(), 1),
                'max_target_minutes' => $prepTargets->isEmpty() ? null : (int) $prepTargets->max(),
            ],
        ];
    }

    public function settings(int $companyId, int $branchId): array
    {
        return [
            'summary' => [
                'profiles' => (int) PosProfile::query()->where('company_id', $companyId)->where('is_active', true)->count(),
                'payment_methods' => (int) PosPaymentMethod::query()->where('company_id', $companyId)->where('is_active', true)->count(),
                'note_templates' => (int) PosNoteTemplate::query()->where('company_id', $companyId)->where('is_active', true)->count(),
                'printers' => (int) PosPreparationPrinter::query()->where('company_id', $companyId)->where('is_active', true)->count(),
                'displays' => (int) PosPreparationDisplay::query()->where('company_id', $companyId)->where('is_active', true)->count(),
            ],
            'profiles' => PosProfile::query()
                ->with(['branch', 'warehouse', 'cashAccount', 'priceList', 'loyaltyProgram', 'noteTemplate', 'defaultPrinter', 'defaultDisplay'])
                ->where('company_id', $companyId)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(),
            'payment_methods' => PosPaymentMethod::query()->with(['branch', 'cashAccount'])->where('company_id', $companyId)->orderBy('sort_order')->orderBy('label')->get(),
            'note_templates' => PosNoteTemplate::query()->where('company_id', $companyId)->orderByDesc('is_default')->orderBy('name')->get(),
            'printers' => PosPreparationPrinter::query()->with('branch')->where('company_id', $companyId)->orderBy('name')->get(),
            'displays' => PosPreparationDisplay::query()->with('branch')->where('company_id', $companyId)->orderBy('name')->get(),
            'branches' => Branch::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(),
            'warehouses' => Warehouse::query()->where('company_id', $companyId)->where('branch_id', $branchId)->where('is_active', true)->orderBy('name')->get(),
            'cash_accounts' => CashAccount::query()
                ->where('company_id', $companyId)
                ->where(function ($query) use ($branchId): void {
                    $query->where('branch_id', $branchId)->orWhereNull('branch_id');
                })
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'price_lists' => PriceList::query()->where('company_id', $companyId)->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(),
            'loyalty_programs' => PosLoyaltyProgram::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(),
        ];
    }

    public function productOptions(int $companyId): Collection
    {
        return Product::query()
            ->where('company_id', $companyId)
            ->saleable()
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'is_variant', 'variant_label', 'parent_product_id']);
    }

    public function customerOptions(int $companyId): Collection
    {
        return Partner::query()
            ->customers()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);
    }

    public function nextCode(string $table, string $prefix, int $companyId): string
    {
        $next = DB::table($table)->where('company_id', $companyId)->count() + 1;

        return strtoupper($prefix).'-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
