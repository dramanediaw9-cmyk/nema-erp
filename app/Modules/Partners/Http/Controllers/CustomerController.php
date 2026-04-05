<?php

namespace App\Modules\Partners\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Core\Company\Models\PaymentTerm;
use App\Modules\Core\Company\Models\PriceList;
use App\Modules\Partners\Models\Partner;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Treasury\Models\Payment;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    public function index(Request $request, CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $filters = $this->filters($request);

        return view('customers.index', [
            'customers' => $this->customerQuery($companyId, $filters)
                ->paginate(15)
                ->withQueryString(),
            'filters' => $filters,
            'cities' => Partner::query()
                ->customers()
                ->where('company_id', $companyId)
                ->whereNotNull('city')
                ->where('city', '!=', '')
                ->select('city')
                ->distinct()
                ->orderBy('city')
                ->pluck('city'),
            'summary' => $this->customerPortfolioSummary($companyId),
        ]);
    }

    public function show(Partner $customer, CurrentWorkspace $workspace): View
    {
        abort_if($workspace->companyId() !== $customer->company_id || ! in_array($customer->type, ['customer', 'both'], true), 403);

        return view('customers.show', [
            'customer' => $customer->load(['company', 'paymentTerm', 'priceList', 'contacts', 'addresses', 'bankAccounts', 'mobileWallets']),
            'invoices' => SalesInvoice::query()
                ->with(['branch', 'approver'])
                ->where('company_id', $customer->company_id)
                ->where('customer_id', $customer->id)
                ->latest('invoice_date')
                ->latest('id')
                ->limit(10)
                ->get(),
            'payments' => Payment::query()
                ->with(['cashAccount', 'creator', 'allocations.allocatable'])
                ->where('company_id', $customer->company_id)
                ->where('partner_id', $customer->id)
                ->latest('payment_date')
                ->latest('id')
                ->limit(10)
                ->get(),
            'journalEntries' => JournalEntry::query()
                ->with(['creator'])
                ->where('company_id', $customer->company_id)
                ->whereHas('lines', fn ($query) => $query->where('partner_id', $customer->id))
                ->latest('entry_date')
                ->latest('id')
                ->limit(10)
                ->get(),
            'stats' => [
                'sales_total' => (float) SalesInvoice::query()
                    ->where('company_id', $customer->company_id)
                    ->where('customer_id', $customer->id)
                    ->where('status', 'validated')
                    ->sum('total'),
                'open_balance' => (float) SalesInvoice::query()
                    ->where('company_id', $customer->company_id)
                    ->where('customer_id', $customer->id)
                    ->whereIn('payment_status', ['unpaid', 'partial'])
                    ->sum('balance_due'),
                'payments_total' => (float) Payment::query()
                    ->where('company_id', $customer->company_id)
                    ->where('partner_id', $customer->id)
                    ->sum('amount'),
                'invoice_count' => (int) SalesInvoice::query()
                    ->where('company_id', $customer->company_id)
                    ->where('customer_id', $customer->id)
                    ->count(),
            ],
        ]);
    }

    public function create(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        return view('customers.create', [
            'partner' => new Partner(['type' => 'customer']),
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
        $data['type'] = 'customer';
        $data['code'] = $data['code'] ?: $this->generateCode($companyId, 'C');
        $data['is_active'] = $request->boolean('is_active', true);

        $partner = Partner::query()->create($data);
        $this->activityLogger->log('customers.create', 'Création client', $partner);

        return redirect()->route('customers.index')->with('success', 'Client créé avec succès.');
    }

    public function edit(Partner $customer, CurrentWorkspace $workspace): View
    {
        abort_if($workspace->companyId() !== $customer->company_id || ! in_array($customer->type, ['customer', 'both'], true), 403);

        return view('customers.edit', [
            'partner' => $customer,
            'paymentTerms' => $this->paymentTerms($customer->company_id),
            'priceLists' => $this->priceLists($customer->company_id),
        ]);
    }

    public function update(Request $request, Partner $customer, CurrentWorkspace $workspace): RedirectResponse
    {
        abort_if($workspace->companyId() !== $customer->company_id || ! in_array($customer->type, ['customer', 'both'], true), 403);

        $data = $this->validatePartner($request, $customer->company_id, $customer->id);
        $data['code'] = $data['code'] ?: $customer->code;
        $data['is_active'] = $request->boolean('is_active', true);

        $customer->update($data);
        $this->activityLogger->log('customers.update', 'Mise à jour client', $customer);

        return redirect()->route('customers.index')->with('success', 'Client mis à jour avec succès.');
    }

    private function paymentTerms(int $companyId)
    {
        return PaymentTerm::query()->where('company_id', $companyId)->where('is_active', true)->orderByDesc('is_default')->orderBy('days')->get();
    }

    private function priceLists(int $companyId)
    {
        return PriceList::query()->where('company_id', $companyId)->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get();
    }

    private function customerQuery(int $companyId, array $filters): Builder
    {
        $stats = $this->customerStatsSubquery($companyId);

        return Partner::query()
            ->customers()
            ->where('partners.company_id', $companyId)
            ->leftJoinSub($stats, 'customer_stats', fn ($join) => $join->on('partners.id', '=', 'customer_stats.customer_id'))
            ->select('partners.*')
            ->selectRaw('COALESCE(customer_stats.sales_total, 0) as sales_total')
            ->selectRaw('COALESCE(customer_stats.open_balance, 0) as open_balance')
            ->selectRaw('COALESCE(customer_stats.overdue_balance, 0) as overdue_balance')
            ->selectRaw('COALESCE(customer_stats.bucket_current, 0) as bucket_current')
            ->selectRaw('COALESCE(customer_stats.bucket_1_30, 0) as bucket_1_30')
            ->selectRaw('COALESCE(customer_stats.bucket_31_60, 0) as bucket_31_60')
            ->selectRaw('COALESCE(customer_stats.bucket_61_plus, 0) as bucket_61_plus')
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
            ->when($filters['balance_state'] === 'open', fn (Builder $query) => $query->whereRaw('COALESCE(customer_stats.open_balance, 0) > 0'))
            ->when($filters['balance_state'] === 'overdue', fn (Builder $query) => $query->whereRaw('COALESCE(customer_stats.overdue_balance, 0) > 0'))
            ->when($filters['balance_state'] === 'clear', fn (Builder $query) => $query->whereRaw('COALESCE(customer_stats.open_balance, 0) = 0'))
            ->orderByDesc('overdue_balance')
            ->orderByDesc('open_balance')
            ->orderBy('partners.name');
    }

    private function customerStatsSubquery(int $companyId)
    {
        $today = now()->toDateString();
        $day30 = now()->subDays(30)->toDateString();
        $day60 = now()->subDays(60)->toDateString();

        return SalesInvoice::query()
            ->select('customer_id')
            ->where('company_id', $companyId)
            ->groupBy('customer_id')
            ->selectRaw("SUM(CASE WHEN status = 'validated' THEN total ELSE 0 END) as sales_total")
            ->selectRaw("SUM(CASE WHEN status = 'validated' AND payment_status IN ('unpaid', 'partial') THEN balance_due ELSE 0 END) as open_balance")
            ->selectRaw("SUM(CASE WHEN status = 'validated' AND payment_status IN ('unpaid', 'partial') AND due_date IS NOT NULL AND due_date < ? THEN balance_due ELSE 0 END) as overdue_balance", [$today])
            ->selectRaw("SUM(CASE WHEN status = 'validated' AND payment_status IN ('unpaid', 'partial') AND (due_date IS NULL OR due_date >= ?) THEN balance_due ELSE 0 END) as bucket_current", [$today])
            ->selectRaw("SUM(CASE WHEN status = 'validated' AND payment_status IN ('unpaid', 'partial') AND due_date < ? AND due_date >= ? THEN balance_due ELSE 0 END) as bucket_1_30", [$today, $day30])
            ->selectRaw("SUM(CASE WHEN status = 'validated' AND payment_status IN ('unpaid', 'partial') AND due_date < ? AND due_date >= ? THEN balance_due ELSE 0 END) as bucket_31_60", [$day30, $day60])
            ->selectRaw("SUM(CASE WHEN status = 'validated' AND payment_status IN ('unpaid', 'partial') AND due_date < ? THEN balance_due ELSE 0 END) as bucket_61_plus", [$day60]);
    }

    private function customerPortfolioSummary(int $companyId): array
    {
        $today = now()->toDateString();

        return [
            'customer_count' => (int) Partner::query()->customers()->where('company_id', $companyId)->count(),
            'active_count' => (int) Partner::query()->customers()->where('company_id', $companyId)->where('is_active', true)->count(),
            'open_balance_total' => (float) SalesInvoice::query()
                ->where('company_id', $companyId)
                ->where('status', 'validated')
                ->whereIn('payment_status', ['unpaid', 'partial'])
                ->sum('balance_due'),
            'overdue_balance_total' => (float) SalesInvoice::query()
                ->where('company_id', $companyId)
                ->where('status', 'validated')
                ->whereIn('payment_status', ['unpaid', 'partial'])
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', $today)
                ->sum('balance_due'),
            'overdue_customer_count' => (int) SalesInvoice::query()
                ->where('company_id', $companyId)
                ->where('status', 'validated')
                ->whereIn('payment_status', ['unpaid', 'partial'])
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', $today)
                ->distinct('customer_id')
                ->count('customer_id'),
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
