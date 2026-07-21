<?php

namespace App\Modules\Pos\Http\Controllers;

use App\Models\User;
use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductCategory;
use App\Modules\Core\Audit\Models\ActivityLog;
use App\Modules\Core\Company\Services\PricingService;
use App\Modules\Inventory\Models\ProductLot;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Partners\Models\Partner;
use App\Modules\Pos\Models\PosComboChoice;
use App\Modules\Pos\Models\PosDraft;
use App\Modules\Pos\Models\PosMenuCategory;
use App\Modules\Pos\Models\PosProductTag;
use App\Modules\Pos\Models\PosSession;
use App\Modules\Pos\Services\PosService;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Treasury\Models\CashAccount;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PosController extends Controller
{
    public function __construct(
        private readonly PosService $posService,
        private readonly ActivityLogger $activityLogger,
        private readonly StockService $stockService,
        private readonly PricingService $pricingService,
    ) {
    }

    public function index(CurrentWorkspace $workspace, Request $request): View
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        $user = $request->user();
        abort_if(! $companyId || ! $branchId || ! $user, 403);

        $currentSession = $this->posService->currentOpenSession($companyId, $branchId, $user->id);
        $isCashier = $user->hasRole('cashier');
        $recentSessions = PosSession::query()
            ->with(['cashAccount', 'warehouse', 'opener', 'closer'])
            ->withCount([
                'salesInvoices as orders_count',
                'payments as payments_count',
                'returns as returns_count',
            ])
            ->withSum('payments as payments_total', 'amount')
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->when($isCashier, fn ($query) => $query->where('opened_by', $user->id))
            ->latest('opened_at')
            ->limit(10)
            ->get();
        $todaySessions = PosSession::query()
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->when($isCashier, fn ($query) => $query->where('opened_by', $user->id))
            ->whereDate('opened_at', now()->toDateString())
            ->get(['status', 'expected_amount', 'variance_amount']);
        $staleOpenSessions = PosSession::query()
            ->with(['cashAccount', 'warehouse', 'opener'])
            ->withCount([
                'salesInvoices as orders_count',
                'payments as payments_count',
                'returns as returns_count',
            ])
            ->withSum('payments as payments_total', 'amount')
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('status', 'open')
            ->where('opened_at', '<', now()->subDay())
            ->when($isCashier, fn ($query) => $query->where('opened_by', $user->id))
            ->oldest('opened_at')
            ->get();

        return view('pos.index', [
            'currentSession' => $currentSession,
            'openSessions' => PosSession::query()
                ->with(['cashAccount', 'warehouse', 'opener'])
                ->where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->where('status', 'open')
                ->orderBy('opened_at')
                ->get(),
            'summary' => $currentSession ? $this->posService->summary($currentSession) : null,
            'recentInvoices' => $currentSession ? $this->posService->recentInvoices($currentSession) : collect(),
            'recentReturns' => $currentSession
                ? $currentSession->returns()->with(['invoice', 'exchangeInvoice', 'payment.cashAccount'])->latest('return_date')->latest('id')->limit(8)->get()
                : collect(),
            'recentSessions' => $recentSessions,
            'staleOpenSessions' => $staleOpenSessions,
            'sessionControl' => [
                'open' => (int) PosSession::query()
                    ->where('company_id', $companyId)
                    ->where('branch_id', $branchId)
                    ->when($isCashier, fn ($query) => $query->where('opened_by', $user->id))
                    ->where('status', 'open')
                    ->count(),
                'today' => (int) $todaySessions->count(),
                'closed_today' => (int) $todaySessions->where('status', 'closed')->count(),
                'expected_today' => round((float) $todaySessions->sum('expected_amount'), 2),
                'variance_today' => round((float) $todaySessions->sum('variance_amount'), 2),
            ],
            'cashAccounts' => CashAccount::query()
                ->where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'warehouses' => Warehouse::query()
                ->where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(),
            'methodOptions' => $this->posService->methodOptions(),
            'cashDenominations' => $this->posService->cashDenominations(),
        ]);
    }

    public function open(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        abort_if(! $companyId || ! $branchId, 403);

        $data = $request->validate([
            'cash_account_id' => ['required', Rule::exists('cash_accounts', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('branch_id', $branchId))],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('branch_id', $branchId))],
            'opening_amount' => ['required', 'numeric', 'min:0'],
            'opening_notes' => ['nullable', 'string'],
            'opening_cash_breakdown' => ['nullable', 'array'],
            'opening_cash_breakdown.*' => ['nullable', 'integer', 'min:0'],
        ]);

        $cashAccount = CashAccount::query()->where('company_id', $companyId)->findOrFail($data['cash_account_id']);
        $warehouse = Warehouse::query()->where('company_id', $companyId)->findOrFail($data['warehouse_id']);
        $session = $this->posService->openSession($companyId, $branchId, $warehouse, $cashAccount, $data, $request->user());

        $this->activityLogger->log('pos.session.open', 'Ouverture session de caisse', $session, [
            'session_number' => $session->session_number,
            'cash_account_id' => $session->cash_account_id,
            'warehouse_id' => $session->warehouse_id,
            'opening_amount' => $session->opening_amount,
            'opening_cash_breakdown' => $session->opening_cash_breakdown,
        ]);

        return redirect()->route('pos.show', $session)->with('success', 'Session de caisse ouverte avec succes.');
    }

        public function show(PosSession $session, CurrentWorkspace $workspace, Request $request): View
    {
        abort_if($workspace->companyId() !== $session->company_id, 403);
        abort_if($request->user()?->hasRole('cashier') && (int) $session->opened_by !== (int) $request->user()->id, 403);

        $session->load(['branch', 'cashAccount', 'warehouse', 'opener', 'closer', 'unlocker', 'salesInvoices.customer', 'payments.cashAccount', 'returns.invoice', 'returns.exchangeInvoice', 'returns.payment.cashAccount']);

        $recentInvoices = $this->posService->recentInvoices($session);

        return view('pos.show', [
            'session' => $session,
            'summary' => $this->posService->summary($session),
            'pendingDrafts' => $this->posService->draftsForSession($session),
            'recentInvoices' => $recentInvoices,
            'ticketRows' => $this->sessionTicketRows($recentInvoices, $session, $request->user()),
            'recentReturns' => $session->returns()->with(['invoice', 'exchangeInvoice', 'payment.cashAccount'])->latest('return_date')->latest('id')->limit(10)->get(),
            'auditLogs' => ActivityLog::query()
                ->with('user:id,name')
                ->where('company_id', $session->company_id)
                ->where('subject_type', $session->getMorphClass())
                ->where('subject_id', $session->id)
                ->latest()
                ->limit(20)
                ->get(),
            'methodOptions' => $this->posService->methodOptions(),
            'cashDenominations' => $this->posService->cashDenominations(),
        ]);
    }

    private function sessionTicketRows(Collection $invoices, PosSession $session, ?User $user): Collection
    {
        return $invoices->map(function (SalesInvoice $invoice) use ($session, $user): array {
            $returnedAmount = (float) $invoice->posReturns->sum('total');
            $createdAt = $invoice->created_at ?: $invoice->invoice_date;
            $status = match (true) {
                filled($invoice->cancelled_at) => ['label' => 'Annule', 'tone' => 'danger'],
                $invoice->payment_status === 'paid' => ['label' => 'Paye', 'tone' => 'success'],
                $invoice->payment_status === 'partial' => ['label' => 'Partiel', 'tone' => 'warning'],
                default => ['label' => 'Valide', 'tone' => 'info'],
            };
            $canPay = (float) $invoice->balance_due > 0.009 && (bool) $user?->hasPermission('payments.validate');
            $canRefund = $session->isOpen()
                && ! filled($invoice->cancelled_at)
                && ((float) $invoice->total - $returnedAmount) > 0.009
                && (bool) $user?->hasPermission('pos.manage');

            $items = $invoice->items->map(fn ($item): array => [
                'description' => $item->description,
                'code' => $item->product?->barcode ?: $item->product?->sku,
                'qty' => (float) $item->qty,
                'unit_price' => (float) $item->unit_price,
                'discount_total' => (float) $item->discount_total,
                'line_total' => (float) $item->line_total,
            ])->values();
            $payments = $invoice->paymentAllocations->map(function ($allocation): array {
                $payment = $allocation->payment;

                return [
                    'method' => ucfirst(str_replace('_', ' ', (string) ($payment?->method ?: 'Paiement'))),
                    'date' => $payment?->payment_date?->format('d/m/Y'),
                    'reference' => $payment?->reference,
                    'cash_account' => $payment?->cashAccount?->name,
                    'direction' => $payment?->direction ?: 'in',
                    'amount' => (float) $allocation->allocated_amount,
                ];
            })->values();
            $returns = $invoice->posReturns->map(fn ($return): array => [
                'number' => $return->return_number,
                'date' => $return->return_date?->format('d/m/Y'),
                'reason' => $return->notes,
                'amount' => (float) $return->total,
            ])->values();
            $history = collect([[
                'label' => 'Ticket cree',
                'detail' => $invoice->creator?->name ?: 'Utilisateur ERP',
                'date' => $invoice->created_at?->format('d/m/Y H:i'),
            ]])->concat($payments->map(fn (array $payment): array => [
                'label' => 'Paiement '.strtolower($payment['method']),
                'detail' => $payment['reference'] ?: 'Encaissement caisse',
                'date' => $payment['date'],
            ]))->concat($returns->map(fn (array $return): array => [
                'label' => 'Retour '.$return['number'],
                'detail' => $return['reason'] ?: 'Remboursement article',
                'date' => $return['date'],
            ]))->values();

            return [
                'id' => $invoice->id,
                'date' => $createdAt?->format('d/m/Y'),
                'time' => $createdAt?->format('H:i'),
                'invoice_number' => $invoice->invoice_number,
                'ticket_reference' => $invoice->pos_sync_key ?: 'Ticket #'.$invoice->id,
                'customer' => $invoice->customer?->name ?: 'Client comptoir',
                'cashier' => $invoice->creator?->name ?: 'Non renseigne',
                'amount' => (float) $invoice->total,
                'discount_total' => (float) $invoice->discount_total,
                'amount_paid' => (float) $invoice->amount_paid,
                'balance_due' => (float) $invoice->balance_due,
                'returned_amount' => $returnedAmount,
                'status' => $status,
                'items' => $items,
                'payments' => $payments,
                'returns' => $returns,
                'history' => $history,
                'search' => collect([$invoice->invoice_number, $invoice->pos_sync_key, $invoice->customer?->name, $invoice->creator?->name])
                    ->concat($items->pluck('description'))
                    ->filter()
                    ->implode(' '),
                'receipt_url' => route('pos.receipt', $invoice),
                'thermal_url' => route('pos.receipt.thermal', $invoice),
                'payment_url' => $canPay ? route('payments.create', ['invoice_id' => $invoice->id]) : '#',
                'return_url' => $canRefund ? route('pos.returns.create', $invoice) : '#',
                'can_pay' => $canPay,
                'can_refund' => $canRefund,
            ];
        })->values();
    }
        public function createSale(CurrentWorkspace $workspace, Request $request): RedirectResponse|View
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        $user = $request->user();
        abort_if(! $companyId || ! $branchId || ! $user, 403);

        $session = $this->resolveAccessibleOpenSession($companyId, $branchId, $user->id, $this->requestedSessionId($request));
        if (! $session) {
            return redirect()->route('pos.index')->with('error', 'Aucune session de caisse ouverte n est accessible pour entrer dans la caisse.');
        }

        $activeProfile = $this->posService->activeProfile($companyId, $branchId);
        $methodConfigs = $this->posService->runtimePaymentMethodConfigs($companyId, $branchId, $activeProfile);
        $methods = $this->posService->runtimeMethodOptions($companyId, $branchId, $activeProfile);
        $hasProductParent = Schema::hasColumn('products', 'parent_id');
        $productRelations = ['category'];
        $productColumns = [
            'id',
            'company_id',
            'category_id',
            'name',
            'sku',
            'barcode',
            'image_path',
            'image_disk',
            'sale_price',
            'type',
            'unit',
            'tracking_type',
        ];

        if ($hasProductParent) {
            $productRelations[] = 'parent';
            array_splice($productColumns, 2, 0, ['parent_id']);
        }

        $productCatalogTotal = Product::query()
            ->where('company_id', $companyId)
            ->saleable()
            ->count();
        $products = Product::query()
            ->with($productRelations)
            ->where('company_id', $companyId)
            ->saleable()
            ->orderBy('name')
            ->limit(30)
            ->get($productColumns);
        $saleableByProduct = $this->saleableQtyByProduct($products, $companyId, $branchId, $session->warehouse_id);
        $priceRules = $this->pricingService->rulesForPriceList(
            $companyId,
            $activeProfile?->price_list_id,
            $products->pluck('id')->all(),
        );
        $menuCategories = PosMenuCategory::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $catalogCategories = ProductCategory::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereHas('products', fn ($query) => $query->saleable())
            ->orderBy('name')
            ->get(['id', 'name']);
        $menuCategoryMap = [];
        foreach ($menuCategories as $menuCategory) {
            foreach (($menuCategory->product_ids ?? []) as $productId) {
                $menuCategoryMap[(int) $productId][] = [
                    'id' => $menuCategory->id,
                    'name' => $menuCategory->name,
                    'color' => $menuCategory->color,
                ];
            }
        }
        $productTags = PosProductTag::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $productTagMap = [];
        foreach ($productTags as $productTag) {
            foreach (($productTag->product_ids ?? []) as $productId) {
                $productTagMap[(int) $productId][] = [
                    'name' => $productTag->name,
                    'color' => $productTag->color,
                ];
            }
        }
        $comboChoices = PosComboChoice::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where(function ($query) use ($branchId): void {
                $query->where('branch_id', $branchId)->orWhereNull('branch_id');
            })
            ->orderByDesc('branch_id')
            ->orderBy('id')
            ->get();
        $comboMap = [];
        foreach ($comboChoices as $comboChoice) {
            if (! $comboChoice->parent_product_id || isset($comboMap[$comboChoice->parent_product_id])) {
                continue;
            }

            $comboMap[$comboChoice->parent_product_id] = $comboChoice;
        }
        $drafts = $this->posService->draftsForSession(
            $session,
            ($activeProfile?->share_open_orders ?? false) ? null : $user->id,
        );
        $paymentAccounts = CashAccount::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where(function ($query) use ($branchId): void {
                $query->where('branch_id', $branchId)->orWhereNull('branch_id');
            })
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return view('pos.sale', [
            'session' => $session,
            'summary' => $this->posService->summary($session),
            'activePosProfile' => $activeProfile ? [
                'id' => $activeProfile->id,
                'name' => $activeProfile->name,
                'price_list_name' => $activeProfile->priceList?->name,
                'loyalty_program_name' => $activeProfile->loyaltyProgram?->name,
                'note_template_name' => $activeProfile->noteTemplate?->name,
                'allow_draft_orders' => (bool) $activeProfile->allow_draft_orders,
                'auto_print_receipt' => (bool) $activeProfile->auto_print_receipt,
                'stock_policy' => $activeProfile->stock_policy ?: 'block',
                'show_stock_quantity' => (bool) $activeProfile->show_stock_quantity,
                'show_product_images' => (bool) $activeProfile->show_product_images,
                'group_products_by_category' => (bool) $activeProfile->group_products_by_category,
                'share_open_orders' => (bool) $activeProfile->share_open_orders,
                'quick_cash_payment' => (bool) $activeProfile->quick_cash_payment,
                'cash_rounding_enabled' => (bool) $activeProfile->cash_rounding_enabled,
                'cash_rounding_precision' => (float) $activeProfile->cash_rounding_precision,
                'allow_tips' => (bool) $activeProfile->allow_tips,
            ] : null,
            'customers' => Partner::query()->customers()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(),
            'categories' => ($activeProfile?->group_products_by_category ?? true)
                ? ($menuCategories->isNotEmpty()
                    ? $menuCategories->map(fn (PosMenuCategory $category) => [
                        'key' => 'menu:'.$category->id,
                        'name' => $category->name,
                        'color' => $category->color,
                    ])
                    : $catalogCategories
                        ->map(fn (ProductCategory $category) => [
                            'key' => 'catalog:'.$category->id,
                            'name' => $category->name,
                            'color' => null,
                        ])
                        ->values())
                : collect(),
            'productCatalogTotal' => $productCatalogTotal,
            'productCatalog' => $products->map(function (Product $product) use ($priceRules, $comboMap, $productTagMap, $menuCategoryMap, $saleableByProduct, $companyId, $branchId, $session) {
                $menuFilters = collect($menuCategoryMap[$product->id] ?? []);
                $tagBadges = collect($productTagMap[$product->id] ?? [])->values();
                /** @var PosComboChoice|null $combo */
                $combo = $comboMap[$product->id] ?? null;
                $basePrice = $this->pricingService->resolveGroupedPrice(
                    $priceRules->get($product->id),
                    1,
                    (float) $product->sale_price,
                );
                $displayPrice = $combo && $combo->pricing_mode === 'fixed' && $combo->price_override !== null
                    ? (float) $combo->price_override
                    : $basePrice;

                $filterKeys = collect();
                if ($product->category_id) {
                    $filterKeys->push('catalog:'.$product->category_id);
                }
                $filterKeys = $filterKeys
                    ->merge($menuFilters->map(fn (array $menuFilter) => 'menu:'.$menuFilter['id']))
                    ->unique()
                    ->values();

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'barcode' => $product->barcode,
                    'image_url' => ($activeProfile?->show_product_images ?? true) ? $product->image_url : null,
                    'price' => $displayPrice,
                    'base_price' => $basePrice,
                    'type' => $product->type,
                    'unit' => $product->unit,
                    'category_id' => $product->category_id,
                    'category_name' => $product->category?->name,
                    'filter_keys' => $filterKeys->all(),
                    'menu_category_names' => $menuFilters->pluck('name')->values()->all(),
                    'tag_names' => $tagBadges->pluck('name')->values()->all(),
                    'tag_badges' => $tagBadges->all(),
                    'combo' => $combo ? [
                        'name' => $combo->name,
                        'pricing_mode' => $combo->pricing_mode,
                        'price_override' => $combo->price_override !== null ? (float) $combo->price_override : null,
                        'component_count' => count($combo->component_product_ids ?? []),
                        'max_selectable' => $combo->max_selectable,
                    ] : null,
                    'available_qty' => $product->type === 'stockable'
                        ? round((float) ($saleableByProduct[$product->id] ?? 0), 3)
                        : null,
                ];
            })->values(),
            'initialItems' => collect(old('items', []))->filter(fn ($item) => filled($item['product_id'] ?? null))->values()->all(),
            'initialPayments' => collect(old('payments', []))->filter(fn ($payment) => is_array($payment) && (filled($payment['amount'] ?? null) || filled($payment['method'] ?? null)))->values()->all(),
            'initialCashReceivedAmount' => old('cash_received_amount', 0),
            'initialNote' => old('notes', $activeProfile?->noteTemplate && $activeProfile->noteTemplate->usage === 'receipt' ? $activeProfile->noteTemplate->content : ''),
            'methods' => $methods,
            'paymentMethodConfigs' => $methodConfigs->values()->all(),
            'allowDraftOrders' => $activeProfile?->allow_draft_orders ?? true,
            'autoPrintReceipt' => $activeProfile?->auto_print_receipt ?? true,
            'stockPolicy' => $activeProfile?->stock_policy ?? 'block',
            'showStockQuantity' => $activeProfile?->show_stock_quantity ?? true,
            'quickCashPayment' => $activeProfile?->quick_cash_payment ?? false,
            'cashRoundingEnabled' => $activeProfile?->cash_rounding_enabled ?? false,
            'cashRoundingPrecision' => (float) ($activeProfile?->cash_rounding_precision ?? 5),
            'paymentAccounts' => $paymentAccounts->map(fn (CashAccount $account) => [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type,
                'branch_id' => $account->branch_id,
            ])->values(),
            'sessionCashAccountId' => $session->cash_account_id,
            'savedDrafts' => $drafts->map(fn (PosDraft $draft) => $this->draftPayload($draft))->values(),
            'activeDraftId' => old('source_draft_id') ?: ($request->integer('draft') ?: null),
            'hasOldPosForm' => $request->session()->hasOldInput(),
        ]);
    }

    public function stockAvailability(CurrentWorkspace $workspace, Request $request): JsonResponse
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        $user = $request->user();
        abort_if(! $companyId || ! $branchId || ! $user, 403);

        $session = $this->resolveAccessibleOpenSession(
            $companyId,
            $branchId,
            $user->id,
            $this->requestedSessionId($request),
        );
        if (! $session) {
            return response()->json([
                'message' => 'Aucune session de caisse ouverte n est accessible pour actualiser le stock.',
            ], 422);
        }

        $products = Product::query()
            ->where('company_id', $companyId)
            ->where('type', 'stockable')
            ->saleable()
            ->get(['id', 'company_id', 'type', 'tracking_type']);
        $saleableByProduct = $this->saleableQtyByProduct($products, $companyId, $branchId, $session->warehouse_id);

        return response()
            ->json([
                'session_id' => $session->id,
                'warehouse_id' => $session->warehouse_id,
                'warehouse_name' => $session->warehouse?->name,
                'products' => $products
                    ->map(fn (Product $product) => [
                        'id' => $product->id,
                        'available_qty' => round((float) ($saleableByProduct[$product->id] ?? 0), 3),
                    ])
                    ->values(),
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function searchSaleProducts(CurrentWorkspace $workspace, Request $request): JsonResponse
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        $user = $request->user();
        abort_if(! $companyId || ! $branchId || ! $user, 403);

        $session = $this->resolveAccessibleOpenSession(
            $companyId,
            $branchId,
            $user->id,
            $this->requestedSessionId($request),
        );
        abort_if(! $session, 403);

        $term = trim((string) $request->query('q', ''));
        $category = trim((string) $request->query('category', ''));
        $limit = min(max($request->integer('limit', 24), 8), 40);
        $page = max($request->integer('page', 1), 1);
        $hasProductParent = Schema::hasColumn('products', 'parent_id');
        $relations = ['category'];
        $columns = [
            'id', 'company_id', 'category_id', 'name', 'sku', 'barcode',
            'image_path', 'image_disk', 'sale_price', 'type', 'unit', 'tracking_type',
        ];
        if ($hasProductParent) {
            $relations[] = 'parent';
            array_splice($columns, 2, 0, ['parent_id']);
        }

        $query = Product::query()
            ->with($relations)
            ->where('company_id', $companyId)
            ->saleable();

        if ($term !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
            $query->where(function ($search) use ($like): void {
                $search->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhere('barcode', 'like', $like);
            });
        }

        if (str_starts_with($category, 'catalog:')) {
            $query->where('category_id', (int) substr($category, 8));
        } elseif (str_starts_with($category, 'menu:')) {
            $menuCategory = PosMenuCategory::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->find((int) substr($category, 5));
            $query->whereIn('id', collect($menuCategory?->product_ids ?? [])->map(fn ($id) => (int) $id)->all());
        }

        $total = (clone $query)->count();
        $pages = max((int) ceil($total / $limit), 1);
        $page = min($page, $pages);
        if ($term !== '') {
            $query->orderByRaw('CASE WHEN barcode = ? OR sku = ? THEN 0 WHEN name LIKE ? THEN 1 ELSE 2 END', [$term, $term, $term.'%']);
        }
        $products = $query->orderBy('name')->forPage($page, $limit)->get($columns);
        $activeProfile = $this->posService->activeProfile($companyId, $branchId);
        $saleableByProduct = $this->saleableQtyByProduct($products, $companyId, $branchId, $session->warehouse_id);
        $priceRules = $this->pricingService->rulesForPriceList(
            $companyId,
            $activeProfile?->price_list_id,
            $products->pluck('id')->all(),
        );

        $payload = $products->map(function (Product $product) use ($activeProfile, $priceRules, $saleableByProduct, $category) {
            $basePrice = $this->pricingService->resolveGroupedPrice(
                $priceRules->get($product->id),
                1,
                (float) $product->sale_price,
            );
            $filterKeys = collect($product->category_id ? ['catalog:'.$product->category_id] : []);
            if (str_starts_with($category, 'menu:')) {
                $filterKeys->push($category);
            }

            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'image_url' => ($activeProfile?->show_product_images ?? true) ? $product->image_url : null,
                'price' => $basePrice,
                'base_price' => $basePrice,
                'type' => $product->type,
                'unit' => $product->unit,
                'category_id' => $product->category_id,
                'category_name' => $product->category?->name,
                'filter_keys' => $filterKeys->unique()->values()->all(),
                'menu_category_names' => [],
                'tag_names' => [],
                'tag_badges' => [],
                'combo' => null,
                'available_qty' => $product->type === 'stockable'
                    ? round((float) ($saleableByProduct[$product->id] ?? 0), 3)
                    : null,
            ];
        })->values();

        return response()->json([
            'products' => $payload,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ]);
    }

    public function storeSale(Request $request, CurrentWorkspace $workspace): RedirectResponse|JsonResponse
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        $user = $request->user();
        abort_if(! $companyId || ! $branchId || ! $user, 403);

        $session = $this->resolveAccessibleOpenSession($companyId, $branchId, $user->id, $this->requestedSessionId($request));
        if (! $session) {
            $message = 'Aucune session de caisse ouverte n est accessible pour enregistrer une vente comptoir.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return redirect()->route('pos.index')->with('error', $message);
        }

        $allowedMethods = array_keys($this->posService->runtimeMethodOptions($companyId, $branchId));

        $payload = $request->validate([
            'customer_id' => ['nullable', Rule::exists('partners', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'sale_date' => ['required', 'date'],
            'method' => ['required', Rule::in($allowedMethods)],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'discount_type' => ['nullable', Rule::in(['none', 'fixed', 'percent'])],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'cash_received_amount' => ['nullable', 'numeric', 'min:0'],
            'payments' => ['nullable', 'array'],
            'payments.*.method' => ['nullable', Rule::in($allowedMethods)],
            'payments.*.amount' => ['nullable', 'numeric', 'min:0'],
            'payments.*.cash_account_id' => ['nullable', Rule::exists('cash_accounts', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'payments.*.label' => ['nullable', 'string', 'max:100'],
            'pos_sync_key' => ['nullable', 'string', 'max:80'],
            'pos_session_id' => ['nullable', Rule::exists('pos_sessions', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('branch_id', $branchId))],
            'source_draft_id' => ['nullable', Rule::exists('pos_drafts', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('branch_id', $branchId)->where('pos_session_id', $session->id))],
        ]);

        $itemsInput = collect($request->input('items', []))
            ->map(fn ($item) => is_array($item) ? $item : [])
            ->filter(fn (array $item) => filled($item['product_id'] ?? null))
            ->values()
            ->all();
        $paymentsInput = collect($request->input('payments', []))
            ->map(fn ($payment) => is_array($payment) ? $payment : [])
            ->filter(fn (array $payment) => filled($payment['method'] ?? null) || filled($payment['amount'] ?? null) || filled($payment['cash_account_id'] ?? null))
            ->values()
            ->all();

        Validator::make(
            ['items' => $itemsInput, 'payments' => $paymentsInput],
            [
                'items' => ['required', 'array', 'min:1'],
                'items.*.product_id' => ['required', Rule::exists('products', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('sale_ok', true)->where('is_active', true))],
                'items.*.description' => ['nullable', 'string', 'max:255'],
                'items.*.qty' => ['required', 'numeric', 'gt:0'],
                'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
                'items.*.discount_type' => ['nullable', Rule::in(['none', 'fixed', 'percent'])],
                'items.*.discount_value' => ['nullable', 'numeric', 'min:0'],
                'payments.*.method' => ['nullable', Rule::in($allowedMethods)],
                'payments.*.amount' => ['nullable', 'numeric', 'min:0'],
                'payments.*.cash_account_id' => ['nullable', Rule::exists('cash_accounts', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            ],
            [
                'items.required' => 'Ajoute au moins un article au ticket.',
                'items.min' => 'Ajoute au moins un article au ticket.',
            ]
        )->validate();

        $sourceDraft = ! empty($payload['source_draft_id'])
            ? PosDraft::query()
                ->where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->where('pos_session_id', $session->id)
                ->findOrFail((int) $payload['source_draft_id'])
            : null;

        $result = $this->posService->processSale($session, $payload, $itemsInput, $paymentsInput, $user);
        $invoice = $result['invoice'];
        $payments = $result['payments'];
        $alreadyProcessed = (bool) ($result['already_processed'] ?? false);

        if ($sourceDraft && ! $alreadyProcessed) {
            $this->posService->deleteDraft($session, $sourceDraft);
        }

        if (! $alreadyProcessed) {
            $this->activityLogger->log('pos.sale.create', 'Creation ticket POS', $invoice, [
                'session_number' => $session->session_number,
                'invoice_number' => $invoice->invoice_number,
                'payment_numbers' => $payments->pluck('payment_number')->values()->all(),
                'cash_received_amount' => (float) $invoice->pos_cash_received,
                'change_due' => (float) $invoice->pos_change_due,
                'subtotal' => $invoice->subtotal,
                'discount_total' => $invoice->discount_total,
                'total' => $invoice->total,
                'source_draft_id' => $sourceDraft?->id,
                'pos_sync_key' => $payload['pos_sync_key'] ?? null,
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $alreadyProcessed ? 'Ticket deja synchronise avec succes.' : 'Ticket enregistre et encaisse avec succes.',
                'invoice' => [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'total' => (float) $invoice->total,
                    'receipt_url' => route('pos.receipt', $invoice, false),
                    'thermal_receipt_url' => route('pos.receipt.thermal', $invoice, false),
                    'already_processed' => $alreadyProcessed,
                ],
                'payments' => $payments->map(fn ($payment) => [
                    'id' => $payment->id,
                    'payment_number' => $payment->payment_number,
                    'amount' => (float) $payment->amount,
                    'method' => $payment->method,
                ])->values()->all(),
            ], $alreadyProcessed ? 200 : 201);
        }

        $message = $alreadyProcessed
            ? 'Ticket deja enregistre, ouverture du ticket existant.'
            : 'Ticket enregistre et encaisse avec succes.';

        if ($request->boolean('print_thermal')) {
            $thermalUrl = route('pos.receipt.thermal', $invoice).'?'.http_build_query([
                'auto_print' => 1,
                'from_pos' => 1,
                'next' => route('pos.receipt', $invoice),
                'return_to' => route('pos.sales.create', ['session' => $session->id]),
            ]);

            return redirect()->to($thermalUrl)->with('success', $message);
        }

        return redirect()->route('pos.receipt', $invoice)->with('success', $message);
    }

    public function storeDraft(Request $request, CurrentWorkspace $workspace): JsonResponse
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        $user = $request->user();
        abort_if(! $companyId || ! $branchId || ! $user, 403);

        $session = $this->resolveAccessibleOpenSession($companyId, $branchId, $user->id, $this->requestedSessionId($request));
        if (! $session) {
            return response()->json([
                'message' => 'Aucune session de caisse ouverte n est accessible pour mettre une commande en attente.',
            ], 422);
        }

        $allowedMethods = array_keys($this->posService->runtimeMethodOptions($companyId, $branchId));

        $payload = $request->validate([
            'draft_id' => ['nullable', Rule::exists('pos_drafts', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('branch_id', $branchId)->where('pos_session_id', $session->id))],
            'label' => ['nullable', 'string', 'max:80'],
            'customer_id' => ['nullable', Rule::exists('partners', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'sale_date' => ['required', 'date'],
            'method' => ['required', Rule::in($allowedMethods)],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'discount_type' => ['nullable', Rule::in(['none', 'fixed', 'percent'])],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'cash_received_amount' => ['nullable', 'numeric', 'min:0'],
            'payments' => ['nullable', 'array'],
            'payments.*.method' => ['nullable', Rule::in($allowedMethods)],
            'payments.*.amount' => ['nullable', 'numeric', 'min:0'],
            'payments.*.cash_account_id' => ['nullable', Rule::exists('cash_accounts', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'payments.*.label' => ['nullable', 'string', 'max:100'],
            'pos_session_id' => ['nullable', Rule::exists('pos_sessions', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('branch_id', $branchId))],
        ]);

        $itemsInput = collect($request->input('items', []))
            ->map(fn ($item) => is_array($item) ? $item : [])
            ->filter(fn (array $item) => filled($item['product_id'] ?? null))
            ->values()
            ->all();
        $paymentsInput = collect($request->input('payments', []))
            ->map(fn ($payment) => is_array($payment) ? $payment : [])
            ->filter(fn (array $payment) => filled($payment['method'] ?? null) || filled($payment['amount'] ?? null) || filled($payment['cash_account_id'] ?? null))
            ->values()
            ->all();

        Validator::make(
            ['items' => $itemsInput, 'payments' => $paymentsInput],
            [
                'items' => ['required', 'array', 'min:1'],
                'items.*.product_id' => ['required', Rule::exists('products', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('sale_ok', true)->where('is_active', true))],
                'items.*.description' => ['nullable', 'string', 'max:255'],
                'items.*.qty' => ['required', 'numeric', 'gt:0'],
                'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
                'items.*.discount_type' => ['nullable', Rule::in(['none', 'fixed', 'percent'])],
                'items.*.discount_value' => ['nullable', 'numeric', 'min:0'],
                'payments.*.method' => ['nullable', Rule::in($allowedMethods)],
                'payments.*.amount' => ['nullable', 'numeric', 'min:0'],
                'payments.*.cash_account_id' => ['nullable', Rule::exists('cash_accounts', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            ],
            [
                'items.required' => 'Ajoute au moins un article avant de mettre la commande en attente.',
                'items.min' => 'Ajoute au moins un article avant de mettre la commande en attente.',
            ]
        )->validate();

        $draft = ! empty($payload['draft_id'])
            ? PosDraft::query()
                ->where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->where('pos_session_id', $session->id)
                ->findOrFail((int) $payload['draft_id'])
            : null;

        $savedDraft = $this->posService->saveDraft($session, $payload, $itemsInput, $paymentsInput, $user, $draft);

        $this->activityLogger->log('pos.draft.save', 'Mise en attente commande POS', $savedDraft, [
            'session_number' => $session->session_number,
            'draft_id' => $savedDraft->id,
            'label' => $savedDraft->label,
            'items_count' => $savedDraft->items_count,
            'total' => $savedDraft->total,
        ]);

        return response()->json([
            'message' => 'Commande mise en attente avec succes.',
            'draft' => $this->draftPayload($savedDraft),
        ]);
    }
    public function destroyDraft(PosDraft $draft, Request $request, CurrentWorkspace $workspace): JsonResponse
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        $user = $request->user();
        abort_if(! $companyId || ! $branchId || ! $user || $draft->company_id !== $companyId || $draft->branch_id !== $branchId, 403);

        $session = PosSession::query()
            ->with(['cashAccount', 'warehouse', 'opener'])
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('status', 'open')
            ->find($draft->pos_session_id);
        if (! $session) {
            return response()->json([
                'message' => 'Ce brouillon ne peut pas etre supprime car sa session n est plus ouverte.',
            ], 422);
        }
        abort_if($user->hasRole('cashier') && (int) $session->opened_by !== (int) $user->id, 403);

        $this->posService->deleteDraft($session, $draft);

        $this->activityLogger->log('pos.draft.delete', 'Suppression brouillon POS', $draft, [
            'session_number' => $session->session_number,
            'draft_id' => $draft->id,
            'label' => $draft->label,
        ]);

        return response()->json([
            'message' => 'Commande brouillon supprimee avec succes.',
        ]);
    }

    public function returnForm(SalesInvoice $sale, CurrentWorkspace $workspace, Request $request): RedirectResponse|View
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        $user = $request->user();
        abort_if(! $companyId || ! $branchId || ! $user || $sale->company_id !== $companyId || $sale->sale_channel !== 'pos', 403);
        $this->authorizeCashierSale($sale, $user);

        $session = $this->resolveAccessibleOpenSession($companyId, $branchId, $user->id, $this->requestedSessionId($request));
        if (! $session) {
            return redirect()->route('pos.index')->with('error', 'Aucune session de caisse ouverte n est accessible pour traiter ce retour.');
        }

        $sale->load(['customer', 'items.product', 'items.posReturnItems', 'posSession.cashAccount', 'posReturns.items']);
        $products = Product::query()
            ->with(['category', 'parent'])
            ->where('company_id', $companyId)
            ->saleable()
            ->orderBy('name')
            ->limit(60)
            ->get();
        $returnableItems = $this->posService->returnableItems($sale);

        return view('pos.return', [
            'session' => $session,
            'invoice' => $sale,
            'returnableItems' => $returnableItems,
            'methods' => $this->posService->methodOptions(),
            'summary' => $this->posService->summary($session),
            'categories' => ProductCategory::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->whereHas('products', fn ($query) => $query->saleable())
                ->orderBy('name')
                ->get(['id', 'name']),
            'exchangeCatalog' => $products->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'image_url' => $product->image_url,
                'price' => (float) $product->sale_price,
                'type' => $product->type,
                'unit' => $product->unit,
                'category_id' => $product->category_id,
                'category_name' => $product->category?->name,
            ])->values(),
            'initialExchangeItems' => collect(old('exchange_items', []))->filter(fn ($item) => filled($item['product_id'] ?? null))->values()->all(),
        ]);
    }
    public function storeReturn(SalesInvoice $sale, Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        $user = $request->user();
        abort_if(! $companyId || ! $branchId || ! $user || $sale->company_id !== $companyId || $sale->sale_channel !== 'pos', 403);
        $this->authorizeCashierSale($sale, $user);

        $session = $this->resolveAccessibleOpenSession($companyId, $branchId, $user->id, $this->requestedSessionId($request));
        if (! $session) {
            return redirect()->route('pos.index')->with('error', 'Aucune session de caisse ouverte n est accessible pour traiter ce retour.');
        }

        $allowedMethods = array_keys($this->posService->runtimeMethodOptions($companyId, $branchId));

        $payload = $request->validate([
            'return_date' => ['required', 'date'],
            'method' => ['required', Rule::in($allowedMethods)],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'return_mode' => ['nullable', Rule::in(['partial', 'cancel_all'])],
        ]);

        $returnableItems = $this->posService->returnableItems($sale);
        $itemsInput = $payload['return_mode'] === 'cancel_all'
            ? $returnableItems
                ->filter(fn (array $item) => $item['remaining_qty'] > 0)
                ->map(fn (array $item) => [
                    'sales_invoice_item_id' => $item['sales_invoice_item_id'],
                    'qty' => $item['remaining_qty'],
                ])
                ->values()
                ->all()
            : collect($request->input('items', []))
                ->map(fn ($item) => is_array($item) ? $item : [])
                ->values()
                ->all();

        $exchangeItemsInput = collect($request->input('exchange_items', []))
            ->map(fn ($item) => is_array($item) ? $item : [])
            ->filter(fn (array $item) => filled($item['product_id'] ?? null))
            ->values()
            ->all();

        Validator::make(
            ['exchange_items' => $exchangeItemsInput],
            [
                'exchange_items.*.product_id' => ['required', Rule::exists('products', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('sale_ok', true)->where('is_active', true))],
                'exchange_items.*.description' => ['nullable', 'string', 'max:255'],
                'exchange_items.*.qty' => ['required', 'numeric', 'gt:0'],
                'exchange_items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
                'exchange_items.*.discount_type' => ['nullable', Rule::in(['none', 'fixed', 'percent'])],
                'exchange_items.*.discount_value' => ['nullable', 'numeric', 'min:0'],
            ]
        )->validate();

        $oldValues = [
            'invoice_number' => $sale->invoice_number,
            'payment_status' => $sale->payment_status,
            'amount_paid' => (float) $sale->amount_paid,
            'balance_due' => (float) $sale->balance_due,
            'returned_total' => (float) $sale->posReturns()->where('status', 'processed')->sum('total'),
        ];

        $result = $this->posService->processReturn($session, $sale, $payload, $itemsInput, $exchangeItemsInput, $user);
        $return = $result['return'];
        $exchangeInvoice = $result['exchange_invoice'];
        $updatedInvoice = $result['invoice'];

        $this->activityLogger->log('pos.sale.return', 'Retour ticket POS', $return, [
            'session_number' => $session->session_number,
            'return_number' => $return->return_number,
            'invoice_number' => $sale->invoice_number,
            'total' => $return->total,
            'mode' => $payload['return_mode'] ?? 'partial',
            'exchange_invoice_number' => $exchangeInvoice?->invoice_number,
            'reason' => $payload['notes'] ?? null,
            'old_values' => $oldValues,
            'new_values' => [
                'invoice_number' => $updatedInvoice->invoice_number,
                'payment_status' => $updatedInvoice->payment_status,
                'amount_paid' => (float) $updatedInvoice->amount_paid,
                'balance_due' => (float) $updatedInvoice->balance_due,
                'returned_total' => (float) $updatedInvoice->posReturns->sum('total'),
            ],
            'returned_items' => collect($itemsInput)->values()->all(),
        ]);

        $message = ($payload['return_mode'] ?? 'partial') === 'cancel_all'
            ? 'Ticket annule et rembourse avec succes.'
            : 'Retour ticket enregistre avec succes.';

        if ($exchangeInvoice) {
            $message .= ' Echange cree sous le numero '.$exchangeInvoice->invoice_number.'.';
        }

        return redirect()->route('pos.show', $session)->with('success', $message);
    }
    public function receipt(SalesInvoice $sale, CurrentWorkspace $workspace, Request $request): View
    {
        abort_if($workspace->companyId() !== $sale->company_id || $sale->sale_channel !== 'pos', 403);
        $this->authorizeCashierSale($sale, $request->user());

        return view('pos.receipt', $this->receiptData($sale));
    }

    public function thermalReceipt(SalesInvoice $sale, CurrentWorkspace $workspace, Request $request): View
    {
        abort_if($workspace->companyId() !== $sale->company_id || $sale->sale_channel !== 'pos', 403);
        $this->authorizeCashierSale($sale, $request->user());

        return view('pos.thermal', $this->receiptData($sale));
    }

    public function report(CurrentWorkspace $workspace, Request $request): View
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        abort_if(! $companyId || ! $branchId, 403);

        $filters = [
            'date' => $request->string('date')->value() ?: now()->toDateString(),
            'warehouse_id' => $request->integer('warehouse_id') ?: null,
            'cash_account_id' => $request->integer('cash_account_id') ?: null,
        ];

        return view('pos.report', [
            'report' => $this->posService->dailyReport($companyId, $branchId, $filters),
            'filters' => $filters,
            'warehouses' => Warehouse::query()->where('company_id', $companyId)->where('branch_id', $branchId)->where('is_active', true)->orderBy('name')->get(),
            'cashAccounts' => CashAccount::query()->where('company_id', $companyId)->where('branch_id', $branchId)->where('is_active', true)->orderBy('name')->get(),
            'methodOptions' => $this->posService->methodOptions(),
            'cashDenominations' => $this->posService->cashDenominations(),
        ]);
    }

    public function printReport(CurrentWorkspace $workspace, Request $request): View
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        abort_if(! $companyId || ! $branchId, 403);

        $filters = [
            'date' => $request->string('date')->value() ?: now()->toDateString(),
            'warehouse_id' => $request->integer('warehouse_id') ?: null,
            'cash_account_id' => $request->integer('cash_account_id') ?: null,
        ];

        return view('pos.report-print', [
            'company' => $workspace->company(),
            'branch' => $workspace->branch(),
            'report' => $this->posService->dailyReport($companyId, $branchId, $filters),
            'filters' => $filters,
            'methodOptions' => $this->posService->methodOptions(),
        ]);
    }

    public function countSheet(PosSession $session, CurrentWorkspace $workspace, Request $request): View
    {
        abort_if($workspace->companyId() !== $session->company_id, 403);
        abort_if($request->user()?->hasRole('cashier') && (int) $session->opened_by !== (int) $request->user()->id, 403);

        $session->load(['branch', 'cashAccount', 'warehouse', 'opener', 'closer']);

        return view('pos.count-sheet', [
            'session' => $session,
            'summary' => $this->posService->summary($session),
            'methodOptions' => $this->posService->methodOptions(),
            'cashDenominations' => $this->posService->cashDenominations(),
        ]);
    }

    public function printSession(PosSession $session, CurrentWorkspace $workspace, Request $request): View
    {
        abort_if($workspace->companyId() !== $session->company_id, 403);
        abort_if($request->user()?->hasRole('cashier') && (int) $session->opened_by !== (int) $request->user()->id, 403);

        $session->load([
            'branch',
            'cashAccount',
            'warehouse',
            'opener',
            'closer',
            'unlocker',
            'salesInvoices.customer',
            'salesInvoices.creator',
            'salesInvoices.items.product',
            'salesInvoices.paymentAllocations.payment.cashAccount',
            'payments.cashAccount',
            'payments.creator',
            'returns.invoice',
            'returns.exchangeInvoice',
            'returns.payment.cashAccount',
        ]);

        return view('pos.session-print', [
            'company' => $workspace->company(),
            'branch' => $workspace->branch(),
            'session' => $session,
            'summary' => $this->posService->summary($session),
            'methodOptions' => $this->posService->methodOptions(),
            'cashDenominations' => $this->posService->cashDenominations(),
        ]);
    }
    public function close(Request $request, PosSession $session, CurrentWorkspace $workspace): RedirectResponse
    {
        abort_if($workspace->companyId() !== $session->company_id, 403);
        abort_if($request->user()?->hasRole('cashier') && (int) $session->opened_by !== (int) $request->user()->id, 403);

        $rules = [
            'closing_notes' => ['nullable', 'string'],
            'closing_cash_breakdown' => ['nullable', 'array'],
            'closing_cash_breakdown.*' => ['nullable', 'integer', 'min:0'],
        ];

        foreach (array_keys($this->posService->methodOptions()) as $method) {
            $rules['counted_methods.'.$method] = ['nullable', 'numeric', 'min:0'];
            $rules['variance_notes.'.$method] = ['nullable', 'string', 'max:255'];
        }

        $data = $request->validate($rules);
        $data['counted_methods'] = $data['counted_methods'] ?? [];
        $data['variance_notes'] = $data['variance_notes'] ?? [];

        $activeProfile = $this->posService->activeProfile($session->company_id, $session->branch_id);
        if ($activeProfile?->max_cash_variance !== null) {
            $expected = (float) $this->posService->summary($session)['expected_amount'];
            $counted = collect($data['counted_methods'])->sum(fn ($amount) => (float) ($amount ?? 0));
            $cashBreakdown = collect($data['closing_cash_breakdown'] ?? [])
                ->sum(fn ($count, $denomination) => (float) $count * (float) $denomination);
            if ($cashBreakdown > 0) {
                $counted -= (float) ($data['counted_methods']['cash'] ?? 0);
                $counted += $cashBreakdown;
            }

            if (abs($counted - $expected) > (float) $activeProfile->max_cash_variance) {
                throw ValidationException::withMessages([
                    'closing_notes' => 'L ecart de caisse depasse la limite autorisee de '.number_format((float) $activeProfile->max_cash_variance, 0, ',', ' ').' XOF. Un responsable doit controler la cloture.',
                ]);
            }
        }

        $session = $this->posService->closeSession($session, $data, $request->user());

        $this->activityLogger->log('pos.session.close', 'Cloture session de caisse', $session, [
            'session_number' => $session->session_number,
            'expected_amount' => $session->expected_amount,
            'closing_amount' => $session->closing_amount,
            'variance_amount' => $session->variance_amount,
            'expected_breakdown' => $session->expected_breakdown,
            'counted_breakdown' => $session->counted_breakdown,
            'closing_cash_breakdown' => $session->closing_cash_breakdown,
            'variance_breakdown' => $session->variance_breakdown,
            'variance_notes' => $session->variance_notes,
        ]);

        return redirect()->route('pos.show', $session)->with('success', 'Session de caisse cloturee avec succes.');
    }

    public function unlock(Request $request, PosSession $session, CurrentWorkspace $workspace): RedirectResponse
    {
        abort_if($workspace->companyId() !== $session->company_id, 403);

        $data = $request->validate([
            'unlock_reason' => ['required', 'string', 'min:8', 'max:1000'],
        ]);

        $oldValues = [
            'status' => $session->status,
            'closed_at' => $session->closed_at?->toIso8601String(),
            'closed_by' => $session->closed_by,
            'expected_amount' => $session->expected_amount,
            'closing_amount' => $session->closing_amount,
            'variance_amount' => $session->variance_amount,
        ];

        $session = $this->posService->unlockSession($session, $data['unlock_reason'], $request->user());

        $this->activityLogger->log('pos.session.unlock', 'Deverrouillage session de caisse', $session, [
            'session_number' => $session->session_number,
            'unlocked_by' => $session->unlocked_by,
            'unlocked_at' => $session->unlocked_at?->toIso8601String(),
            'unlock_reason' => $session->unlock_reason,
            'reason' => $session->unlock_reason,
            'old_values' => $oldValues,
            'new_values' => [
                'status' => $session->status,
                'unlocked_by' => $session->unlocked_by,
                'unlocked_at' => $session->unlocked_at?->toIso8601String(),
            ],
        ]);

        return redirect()->route('pos.show', $session)->with('success', 'Session de caisse deverrouillee avec trace d audit.');
    }

    private function requestedSessionId(Request $request, ?int $fallback = null): ?int
    {
        return $request->integer('pos_session_id') ?: $request->integer('session') ?: ($fallback ?: null);
    }

    private function resolveAccessibleOpenSession(int $companyId, int $branchId, int $userId, ?int $requestedSessionId = null): ?PosSession
    {
        if ($requestedSessionId) {
            return PosSession::query()
                ->with(['cashAccount', 'warehouse', 'opener'])
                ->where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->where('status', 'open')
                ->when(User::query()->find($userId)?->hasRole('cashier'), fn ($query) => $query->where('opened_by', $userId))
                ->find($requestedSessionId);
        }

        return $this->posService->currentOpenSession($companyId, $branchId, $userId);
    }

    private function authorizeCashierSale(SalesInvoice $sale, ?User $user): void
    {
        if (! $user?->hasRole('cashier')) {
            return;
        }

        $belongsToCashier = PosSession::query()
            ->whereKey($sale->pos_session_id)
            ->where('opened_by', $user->id)
            ->exists();

        abort_unless($belongsToCashier, 403);
    }
    private function saleableQtyByProduct(Collection $products, int $companyId, int $branchId, ?int $warehouseId = null): array
    {
        $stockableProducts = $products
            ->where('type', 'stockable')
            ->values();

        if ($stockableProducts->isEmpty()) {
            return [];
        }

        $stockableIds = $stockableProducts->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $baseStockByProduct = StockMovement::query()
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->whereIn('product_id', $stockableIds->all())
            ->when($warehouseId, fn ($query, int $resolvedWarehouseId) => $query->where('warehouse_id', $resolvedWarehouseId))
            ->groupBy('product_id')
            ->selectRaw('product_id, COALESCE(SUM(quantity_in - quantity_out), 0) as saleable_qty')
            ->pluck('saleable_qty', 'product_id')
            ->map(fn ($value) => (float) $value)
            ->all();

        $trackedIds = $stockableProducts
            ->filter(fn (Product $product) => in_array($product->tracking_type, ['lot', 'serial'], true))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($trackedIds->isEmpty()) {
            return $baseStockByProduct;
        }

        $trackedSaleable = ProductLot::query()
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->whereIn('product_id', $trackedIds->all())
            ->where('quantity_available', '>', 0)
            ->when($warehouseId, fn ($query, int $resolvedWarehouseId) => $query->where('warehouse_id', $resolvedWarehouseId))
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhereDate('expires_at', '>=', now()->toDateString());
            })
            ->groupBy('product_id')
            ->selectRaw('product_id, COALESCE(SUM(quantity_available), 0) as saleable_qty')
            ->pluck('saleable_qty', 'product_id')
            ->map(fn ($value) => (float) $value)
            ->all();

        foreach ($trackedSaleable as $productId => $saleableQty) {
            $baseStockByProduct[(int) $productId] = $saleableQty;
        }

        return $baseStockByProduct;
    }

    private function receiptData(SalesInvoice $sale): array
    {
        $invoice = $sale->load([
            'company',
            'branch',
            'warehouse',
            'customer',
            'creator',
            'posSession.cashAccount',
            'items.product',
            'items.posReturnItems',
            'paymentAllocations.payment.cashAccount',
            'preparationTickets.items.product',
            'preparationTickets.printer',
            'preparationTickets.display',
            'posReturns.items.product',
            'posReturns.payment.cashAccount',
            'posReturns.exchangeInvoice',
        ]);

        $payments = $invoice->paymentAllocations
            ->pluck('payment')
            ->filter()
            ->unique('id')
            ->sortBy('id')
            ->values();

        return [
            'invoice' => $invoice,
            'payments' => $payments,
            'payment' => $payments->first(),
            'preparationTickets' => $invoice->preparationTickets,
            'receiptProfile' => $this->posService->activeProfile($invoice->company_id, $invoice->branch_id),
        ];
    }

    private function draftPayload(PosDraft $draft): array
    {
        $draft->loadMissing(['customer', 'creator', 'updater']);

        return [
            'id' => $draft->id,
            'label' => $draft->label,
            'customer_id' => $draft->customer_id,
            'customer_name' => $draft->customer?->name,
            'sale_date' => $draft->sale_date?->toDateString(),
            'method' => $draft->method,
            'reference' => $draft->reference,
            'notes' => $draft->notes,
            'discount_type' => $draft->discount_type,
            'discount_value' => (float) $draft->discount_value,
            'items' => collect($draft->items ?? [])->values()->all(),
            'payments' => collect($draft->payments ?? [])->values()->all(),
            'cash_received_amount' => (float) $draft->cash_received_amount,
            'items_count' => (int) $draft->items_count,
            'total' => (float) $draft->total,
            'last_activity_at' => $draft->last_activity_at?->toIso8601String(),
            'updated_by_name' => $draft->updater?->name ?? $draft->creator?->name,
        ];
    }
}

