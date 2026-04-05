<?php

namespace App\Modules\Partners\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Core\Company\Models\PaymentTerm;
use App\Modules\Core\Company\Models\PriceList;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Partners\Models\Partner;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Purchases\Services\SupplierPerformanceService;
use App\Modules\Treasury\Models\Payment;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly SupplierPerformanceService $supplierPerformanceService,
    ) {
    }

    public function index(Request $request, CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $filters = $this->filters($request);

        return view('suppliers.index', [
            'suppliers' => $this->supplierQuery($companyId, $filters)
                ->paginate(15)
                ->withQueryString(),
            'filters' => $filters,
            'cities' => Partner::query()
                ->suppliers()
                ->where('company_id', $companyId)
                ->whereNotNull('city')
                ->where('city', '!=', '')
                ->select('city')
                ->distinct()
                ->orderBy('city')
                ->pluck('city'),
            'summary' => $this->supplierPortfolioSummary($companyId),
        ]);
    }

    public function show(Partner $supplier, CurrentWorkspace $workspace): View
    {
        abort_if($workspace->companyId() !== $supplier->company_id || ! in_array($supplier->type, ['supplier', 'both'], true), 403);

        return view('suppliers.show', [
            'supplier' => $supplier->load(['company', 'paymentTerm', 'priceList', 'contacts', 'addresses', 'bankAccounts', 'mobileWallets']),
            'bills' => PurchaseBill::query()
                ->with(['branch', 'approver'])
                ->where('company_id', $supplier->company_id)
                ->where('supplier_id', $supplier->id)
                ->latest('bill_date')
                ->latest('id')
                ->limit(10)
                ->get(),
            'payments' => Payment::query()
                ->with(['cashAccount', 'creator', 'allocations.allocatable'])
                ->where('company_id', $supplier->company_id)
                ->where('partner_id', $supplier->id)
                ->latest('payment_date')
                ->latest('id')
                ->limit(10)
                ->get(),
            'expenses' => Expense::query()
                ->with(['category'])
                ->where('company_id', $supplier->company_id)
                ->where('supplier_id', $supplier->id)
                ->latest('expense_date')
                ->latest('id')
                ->limit(10)
                ->get(),
            'journalEntries' => JournalEntry::query()
                ->with(['creator'])
                ->where('company_id', $supplier->company_id)
                ->whereHas('lines', fn ($query) => $query->where('partner_id', $supplier->id))
                ->latest('entry_date')
                ->latest('id')
                ->limit(10)
                ->get(),
            'stats' => [
                'purchase_total' => (float) PurchaseBill::query()
                    ->where('company_id', $supplier->company_id)
                    ->where('supplier_id', $supplier->id)
                    ->where('status', 'validated')
                    ->sum('total'),
                'open_balance' => (float) PurchaseBill::query()
                    ->where('company_id', $supplier->company_id)
                    ->where('supplier_id', $supplier->id)
                    ->whereIn('payment_status', ['unpaid', 'partial'])
                    ->sum('balance_due'),
                'payments_total' => (float) Payment::query()
                    ->where('company_id', $supplier->company_id)
                    ->where('partner_id', $supplier->id)
                    ->sum('amount'),
                'expenses_total' => (float) Expense::query()
                    ->where('company_id', $supplier->company_id)
                    ->where('supplier_id', $supplier->id)
                    ->sum('total'),
            ],
            'performance' => $this->supplierPerformanceService->summaryForSupplier($supplier->company_id, $supplier->id),
        ]);
    }

    public function create(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        return view('suppliers.create', [
            'partner' => new Partner(['type' => 'supplier']),
            'paymentTerms' => $this->paymentTerms($companyId),
            'priceLists' => $this->priceLists($companyId),
        ]);
    }

    public function store(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $data = $this->validatePartner($request, $companyId);
        $data['company_id'] = $companyId;
        $data['type'] = 'supplier';
        $data['code'] = $data['code'] ?: $this->generateCode($companyId, 'F');
        $data['is_active'] = $request->boolean('is_active', true);

        $partner = Partner::query()->create($data);
        $this->activityLogger->log('suppliers.create', 'Création fournisseur', $partner);

        return redirect()->route('suppliers.index')->with('success', 'Fournisseur créé avec succès.');
    }

    public function edit(Partner $supplier, CurrentWorkspace $workspace): View
    {
        abort_if($workspace->companyId() !== $supplier->company_id || ! in_array($supplier->type, ['supplier', 'both'], true), 403);

        return view('suppliers.edit', [
            'partner' => $supplier,
            'paymentTerms' => $this->paymentTerms($supplier->company_id),
            'priceLists' => $this->priceLists($supplier->company_id),
        ]);
    }

    public function update(Request $request, Partner $supplier, CurrentWorkspace $workspace): RedirectResponse
    {
        abort_if($workspace->companyId() !== $supplier->company_id || ! in_array($supplier->type, ['supplier', 'both'], true), 403);

        $data = $this->validatePartner($request, $supplier->company_id, $supplier->id);
        $data['code'] = $data['code'] ?: $supplier->code;
        $data['is_active'] = $request->boolean('is_active', true);

        $supplier->update($data);
        $this->activityLogger->log('suppliers.update', 'Mise à jour fournisseur', $supplier);

        return redirect()->route('suppliers.index')->with('success', 'Fournisseur mis à jour avec succès.');
    }

    private function paymentTerms(int $companyId)
    {
        return PaymentTerm::query()->where('company_id', $companyId)->where('is_active', true)->orderByDesc('is_default')->orderBy('days')->get();
    }

    private function priceLists(int $companyId)
    {
        return PriceList::query()->where('company_id', $companyId)->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get();
    }

    private function supplierQuery(int $companyId, array $filters): Builder
    {
        $stats = $this->supplierStatsSubquery($companyId);

        return Partner::query()
            ->suppliers()
            ->where('partners.company_id', $companyId)
            ->leftJoinSub($stats, 'supplier_stats', fn ($join) => $join->on('partners.id', '=', 'supplier_stats.supplier_id'))
            ->select('partners.*')
            ->selectRaw('COALESCE(supplier_stats.purchase_total, 0) as purchase_total')
            ->selectRaw('COALESCE(supplier_stats.open_balance, 0) as open_balance')
            ->selectRaw('COALESCE(supplier_stats.overdue_balance, 0) as overdue_balance')
            ->selectRaw('COALESCE(supplier_stats.bucket_current, 0) as bucket_current')
            ->selectRaw('COALESCE(supplier_stats.bucket_1_30, 0) as bucket_1_30')
            ->selectRaw('COALESCE(supplier_stats.bucket_31_60, 0) as bucket_31_60')
            ->selectRaw('COALESCE(supplier_stats.bucket_61_plus, 0) as bucket_61_plus')
            ->when($filters['search'], function (Builder $query, string $search) {
                $like = '%'.$search.'%';

                $query->where(function (Builder $nested) use ($like) {
                    $nested->where('partners.code', 'like', $like)
                        ->orWhere('partners.name', 'like', $like)
                        ->orWhere('partners.phone', 'like', $like)
                        ->orWhere('partners.email', 'like', $like)
                        ->orWhere('partners.nif', 'like', $like);
                });
            })
            ->when($filters['city'], fn (Builder $query, string $city) => $query->where('partners.city', $city))
            ->when($filters['status'] !== null, fn (Builder $query) => $query->where('partners.is_active', $filters['status'] === 'active'))
            ->when($filters['balance_state'] === 'open', fn (Builder $query) => $query->whereRaw('COALESCE(supplier_stats.open_balance, 0) > 0'))
            ->when($filters['balance_state'] === 'overdue', fn (Builder $query) => $query->whereRaw('COALESCE(supplier_stats.overdue_balance, 0) > 0'))
            ->when($filters['balance_state'] === 'clear', fn (Builder $query) => $query->whereRaw('COALESCE(supplier_stats.open_balance, 0) = 0'))
            ->orderByDesc('overdue_balance')
            ->orderByDesc('open_balance')
            ->orderBy('partners.name');
    }

    private function supplierStatsSubquery(int $companyId)
    {
        $today = now()->toDateString();
        $day30 = now()->subDays(30)->toDateString();
        $day60 = now()->subDays(60)->toDateString();

        return PurchaseBill::query()
            ->select('supplier_id')
            ->where('company_id', $companyId)
            ->groupBy('supplier_id')
            ->selectRaw("SUM(CASE WHEN status = 'validated' THEN total ELSE 0 END) as purchase_total")
            ->selectRaw("SUM(CASE WHEN status = 'validated' AND payment_status IN ('unpaid', 'partial') THEN balance_due ELSE 0 END) as open_balance")
            ->selectRaw("SUM(CASE WHEN status = 'validated' AND payment_status IN ('unpaid', 'partial') AND due_date IS NOT NULL AND due_date < ? THEN balance_due ELSE 0 END) as overdue_balance", [$today])
            ->selectRaw("SUM(CASE WHEN status = 'validated' AND payment_status IN ('unpaid', 'partial') AND (due_date IS NULL OR due_date >= ?) THEN balance_due ELSE 0 END) as bucket_current", [$today])
            ->selectRaw("SUM(CASE WHEN status = 'validated' AND payment_status IN ('unpaid', 'partial') AND due_date < ? AND due_date >= ? THEN balance_due ELSE 0 END) as bucket_1_30", [$today, $day30])
            ->selectRaw("SUM(CASE WHEN status = 'validated' AND payment_status IN ('unpaid', 'partial') AND due_date < ? AND due_date >= ? THEN balance_due ELSE 0 END) as bucket_31_60", [$day30, $day60])
            ->selectRaw("SUM(CASE WHEN status = 'validated' AND payment_status IN ('unpaid', 'partial') AND due_date < ? THEN balance_due ELSE 0 END) as bucket_61_plus", [$day60]);
    }

    private function supplierPortfolioSummary(int $companyId): array
    {
        $today = now()->toDateString();

        return [
            'supplier_count' => (int) Partner::query()->suppliers()->where('company_id', $companyId)->count(),
            'active_count' => (int) Partner::query()->suppliers()->where('company_id', $companyId)->where('is_active', true)->count(),
            'open_balance_total' => (float) PurchaseBill::query()
                ->where('company_id', $companyId)
                ->where('status', 'validated')
                ->whereIn('payment_status', ['unpaid', 'partial'])
                ->sum('balance_due'),
            'overdue_balance_total' => (float) PurchaseBill::query()
                ->where('company_id', $companyId)
                ->where('status', 'validated')
                ->whereIn('payment_status', ['unpaid', 'partial'])
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', $today)
                ->sum('balance_due'),
            'overdue_supplier_count' => (int) PurchaseBill::query()
                ->where('company_id', $companyId)
                ->where('status', 'validated')
                ->whereIn('payment_status', ['unpaid', 'partial'])
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', $today)
                ->distinct('supplier_id')
                ->count('supplier_id'),
        ];
    }

    private function filters(Request $request): array
    {
        $status = $request->string('status')->trim()->value() ?: null;
        if (! in_array($status, ['active', 'inactive'], true)) {
            $status = null;
        }

        $balanceState = $request->string('balance_state')->trim()->value() ?: null;
        if (! in_array($balanceState, ['open', 'overdue', 'clear'], true)) {
            $balanceState = null;
        }

        return [
            'search' => $request->string('search')->trim()->value() ?: null,
            'city' => $request->string('city')->trim()->value() ?: null,
            'status' => $status,
            'balance_state' => $balanceState,
        ];
    }

    private function validatePartner(Request $request, int $companyId, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('partners', 'code')->where(fn ($query) => $query->where('company_id', $companyId))->ignore($ignoreId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'nif' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'payment_term_id' => ['nullable', Rule::exists('payment_terms', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'price_list_id' => ['nullable', Rule::exists('price_lists', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function generateCode(int $companyId, string $prefix): string
    {
        $number = Partner::query()->where('company_id', $companyId)->count() + 1;

        do {
            $code = sprintf('%s%04d', Str::upper($prefix), $number);
            $exists = Partner::query()->where('company_id', $companyId)->where('code', $code)->exists();
            $number++;
        } while ($exists);

        return $code;
    }
}



