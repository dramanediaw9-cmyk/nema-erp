<?php

namespace App\Modules\Pos\Services;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Company\Models\DocumentSequence;
use App\Modules\Core\Company\Services\DocumentNumberService;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Partners\Models\Partner;
use App\Modules\Pos\Models\PosDraft;
use App\Modules\Pos\Models\PosPaymentMethod;
use App\Modules\Pos\Models\PosProfile;
use App\Modules\Pos\Models\PosReturn;
use App\Modules\Pos\Models\PosReturnItem;
use App\Modules\Pos\Models\PosSession;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesInvoiceItem;
use App\Modules\Sales\Services\SalesInvoiceService;
use App\Modules\Treasury\Models\CashAccount;
use App\Modules\Treasury\Models\Payment;
use App\Modules\Treasury\Services\PaymentService;
use App\Support\PaymentMethodCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosService
{
    public function __construct(
        private readonly DocumentNumberService $documentNumberService,
        private readonly SalesInvoiceService $salesInvoiceService,
        private readonly PaymentService $paymentService,
        private readonly StockService $stockService,
        private readonly PosPreparationService $preparationService,
    ) {}

    public function methodOptions(): array
    {
        return PaymentMethodCatalog::options();
    }

    public function activeProfile(int $companyId, int $branchId): ?PosProfile
    {
        return PosProfile::query()
            ->with(['priceList', 'loyaltyProgram', 'noteTemplate', 'defaultPrinter', 'defaultDisplay', 'cashAccount', 'warehouse'])
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first()
            ?? PosProfile::query()
                ->with(['priceList', 'loyaltyProgram', 'noteTemplate', 'defaultPrinter', 'defaultDisplay', 'cashAccount', 'warehouse'])
                ->where('company_id', $companyId)
                ->whereNull('branch_id')
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->first();
    }

    public function runtimePaymentMethodConfigs(int $companyId, int $branchId, ?PosProfile $profile = null): Collection
    {
        $profile ??= $this->activeProfile($companyId, $branchId);

        $configured = PosPaymentMethod::query()
            ->with('cashAccount')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where(function (Builder $query) use ($branchId): void {
                $query->where('branch_id', $branchId)->orWhereNull('branch_id');
            })
            ->get()
            ->sortBy([
                fn (PosPaymentMethod $method) => $method->branch_id === $branchId ? 0 : 1,
                fn (PosPaymentMethod $method) => $method->is_default ? 0 : 1,
                fn (PosPaymentMethod $method) => (int) $method->sort_order,
                fn (PosPaymentMethod $method) => $method->label,
            ])
            ->unique('method_code')
            ->keyBy('method_code');

        $orderedCodes = collect($profile?->active_payment_methods ?? [])
            ->filter(fn ($code) => filled($code))
            ->values();

        if ($orderedCodes->isEmpty()) {
            $orderedCodes = $configured->keys()->values();
        }

        if ($orderedCodes->isEmpty()) {
            $orderedCodes = collect(PaymentMethodCatalog::values());
        }

        return $orderedCodes->map(function (string $methodCode) use ($configured) {
            /** @var PosPaymentMethod|null $method */
            $method = $configured->get($methodCode);

            return [
                'method_code' => $methodCode,
                'label' => $method?->label ?: PaymentMethodCatalog::label($methodCode),
                'transaction_label' => $method?->transaction_label,
                'cash_account_id' => $method?->cash_account_id,
                'requires_reference' => (bool) ($method?->requires_reference ?? in_array($methodCode, ['wave', 'orange_money', 'moov_money', 'mobile_money', 'bank_transfer', 'cheque'], true)),
                'supports_change' => (bool) ($method?->supports_change ?? $methodCode === 'cash'),
                'is_default' => (bool) ($method?->is_default ?? false),
            ];
        })->values();
    }

    public function runtimeMethodOptions(int $companyId, int $branchId, ?PosProfile $profile = null): array
    {
        return $this->runtimePaymentMethodConfigs($companyId, $branchId, $profile)
            ->mapWithKeys(fn (array $config) => [$config['method_code'] => $config['label']])
            ->all();
    }

    public function cashDenominations(): array
    {
        return [
            '10000' => 'Billet 10 000',
            '5000' => 'Billet 5 000',
            '2000' => 'Billet 2 000',
            '1000' => 'Billet 1 000',
            '500' => 'Billet 500',
            '250' => 'Piece 250',
            '200' => 'Piece 200',
            '100' => 'Piece 100',
            '50' => 'Piece 50',
            '25' => 'Piece 25',
            '10' => 'Piece 10',
            '5' => 'Piece 5',
            '1' => 'Piece 1',
        ];
    }

    public function currentOpenSession(int $companyId, int $branchId, int $userId): ?PosSession
    {
        return PosSession::query()
            ->with(['cashAccount', 'warehouse', 'opener'])
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('opened_by', $userId)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();
    }

    public function openSession(int $companyId, int $branchId, Warehouse $warehouse, CashAccount $cashAccount, array $payload, User $user): PosSession
    {
        if ($warehouse->company_id !== $companyId || $warehouse->branch_id !== $branchId || ! $warehouse->is_active) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'Selectionne un entrepot actif de l agence courante.',
            ]);
        }

        if ($cashAccount->company_id !== $companyId || $cashAccount->branch_id !== $branchId || ! $cashAccount->is_active) {
            throw ValidationException::withMessages([
                'cash_account_id' => 'Selectionne un compte de caisse actif pour l agence courante.',
            ]);
        }

        $openingCashBreakdown = $this->normalizeCashDenominationCounts($payload['opening_cash_breakdown'] ?? []);
        $openingBreakdownTotal = $this->cashBreakdownTotal($openingCashBreakdown);

        if ($openingBreakdownTotal <= 0) {
            $openingCashBreakdown = null;
            $openingAmount = (float) ($payload['opening_amount'] ?? 0);
        } else {
            $openingAmount = $openingBreakdownTotal;
        }

        return DB::transaction(function () use ($companyId, $branchId, $warehouse, $cashAccount, $payload, $user, $openingCashBreakdown, $openingAmount) {
            $existingSession = PosSession::query()
                ->where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->where('opened_by', $user->id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            if ($existingSession) {
                throw ValidationException::withMessages([
                    'session' => 'Une session de caisse est deja ouverte pour cet utilisateur.',
                ]);
            }

            $this->ensureSequence($companyId, 'pos_session', 'POS-{BRANCH}-{YEAR}-');

            return PosSession::query()->create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'warehouse_id' => $warehouse->id,
                'cash_account_id' => $cashAccount->id,
                'session_number' => $this->documentNumberService->nextNumber(
                    companyId: $companyId,
                    documentType: 'pos_session',
                    branchId: $branchId,
                    date: now()->toDateString(),
                ),
                'status' => 'open',
                'opening_amount' => $openingAmount,
                'opening_cash_breakdown' => $openingCashBreakdown,
                'opening_notes' => $payload['opening_notes'] ?? null,
                'opened_at' => now(),
                'opened_by' => $user->id,
            ])->load(['cashAccount', 'warehouse', 'opener']);
        });
    }

    public function closeSession(PosSession $session, array $payload, User $user): PosSession
    {
        return DB::transaction(function () use ($session, $payload, $user) {
            $session = PosSession::query()
                ->with(['payments'])
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $session->isOpen()) {
                throw ValidationException::withMessages([
                    'session' => 'Cette session de caisse est deja cloturee.',
                ]);
            }

            $expectedBreakdown = $this->expectedMethodBreakdown($session);
            $countedBreakdown = $this->normalizeMethodBreakdown($payload['counted_methods'] ?? []);
            $closingCashBreakdown = $this->normalizeCashDenominationCounts($payload['closing_cash_breakdown'] ?? []);
            $closingCashTotal = $this->cashBreakdownTotal($closingCashBreakdown);

            if ($closingCashTotal > 0) {
                $countedBreakdown['cash'] = $closingCashTotal;
            } else {
                $closingCashBreakdown = null;
                $countedBreakdown['cash'] = (float) ($countedBreakdown['cash'] ?? 0);
            }

            $varianceBreakdown = [];
            $varianceNotes = $this->normalizeVarianceNotes($payload['variance_notes'] ?? []);

            foreach ($this->methodOptions() as $method => $label) {
                $varianceBreakdown[$method] = round(($countedBreakdown[$method] ?? 0) - ($expectedBreakdown[$method] ?? 0), 2);

                if (abs((float) $varianceBreakdown[$method]) > 0.009 && blank($varianceNotes[$method] ?? null)) {
                    throw ValidationException::withMessages([
                        'variance_notes.'.$method => 'Justifie l ecart pour le mode '.$label.'.',
                    ]);
                }
            }

            $expectedAmount = round(array_sum($expectedBreakdown), 2);
            $closingAmount = round(array_sum($countedBreakdown), 2);
            $varianceAmount = round($closingAmount - $expectedAmount, 2);

            $session->update([
                'status' => 'closed',
                'expected_amount' => $expectedAmount,
                'expected_breakdown' => $expectedBreakdown,
                'closing_amount' => $closingAmount,
                'counted_breakdown' => $countedBreakdown,
                'closing_cash_breakdown' => $closingCashBreakdown,
                'variance_amount' => $varianceAmount,
                'variance_breakdown' => $varianceBreakdown,
                'variance_notes' => $varianceNotes,
                'closing_notes' => $payload['closing_notes'] ?? null,
                'closed_at' => now(),
                'closed_by' => $user->id,
            ]);

            return $session->fresh(['cashAccount', 'warehouse', 'opener', 'closer', 'payments', 'returns']);
        });
    }

    public function unlockSession(PosSession $session, string $reason, User $user): PosSession
    {
        return DB::transaction(function () use ($session, $reason, $user) {
            $session = PosSession::query()
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($session->isOpen()) {
                throw ValidationException::withMessages([
                    'session' => 'Cette session de caisse est deja ouverte.',
                ]);
            }

            $cleanReason = trim($reason);
            if ($cleanReason === '') {
                throw ValidationException::withMessages([
                    'unlock_reason' => 'Le motif de deverrouillage est obligatoire.',
                ]);
            }

            $session->update([
                'status' => 'open',
                'unlocked_at' => now(),
                'unlocked_by' => $user->id,
                'unlock_reason' => $cleanReason,
            ]);

            return $session->fresh(['cashAccount', 'warehouse', 'opener', 'closer', 'unlocker', 'payments', 'returns']);
        });
    }

    public function processSale(PosSession $session, array $payload, array $itemsInput, array $paymentsInput, User $user): array
    {
        return DB::transaction(function () use ($session, $payload, $itemsInput, $paymentsInput, $user) {
            $session = PosSession::query()
                ->with(['cashAccount', 'warehouse'])
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $session->isOpen()) {
                throw ValidationException::withMessages([
                    'session' => 'La session de caisse doit etre ouverte pour enregistrer une vente comptoir.',
                ]);
            }

            $syncKey = filled($payload['pos_sync_key'] ?? null)
                ? trim((string) $payload['pos_sync_key'])
                : null;

            if ($syncKey) {
                $existingInvoice = SalesInvoice::query()
                    ->with(['customer', 'branch', 'warehouse', 'posSession', 'items.product', 'items.posReturnItems', 'paymentAllocations.payment.cashAccount', 'posReturns.items'])
                    ->where('company_id', $session->company_id)
                    ->where('pos_session_id', $session->id)
                    ->where('sale_channel', 'pos')
                    ->where('pos_sync_key', $syncKey)
                    ->first();

                if ($existingInvoice) {
                    $this->preparationService->ensureTicketsForInvoice($existingInvoice, $user->id);

                    return [
                        'session' => $session->fresh(['cashAccount', 'warehouse']),
                        'invoice' => $existingInvoice,
                        'payments' => $existingInvoice->paymentAllocations
                            ->pluck('payment')
                            ->filter(fn ($payment) => $payment instanceof Payment)
                            ->map(fn (Payment $payment) => $payment->fresh(['cashAccount', 'partner', 'posSession']))
                            ->values(),
                        'already_processed' => true,
                    ];
                }
            }

            $customer = ! empty($payload['customer_id'])
                ? Partner::query()
                    ->customers()
                    ->where('company_id', $session->company_id)
                    ->findOrFail((int) $payload['customer_id'])
                : $this->walkInCustomer($session->company_id);

            $items = $this->salesInvoiceService->normalizeItems($session->company_id, $itemsInput, null, $user);
            $this->salesInvoiceService->assertCreatable(
                $session->company_id,
                $session->branch_id,
                $items,
                $session->warehouse_id,
                null,
                false,
            );

            $invoice = $this->salesInvoiceService->createValidated(
                companyId: $session->company_id,
                branchId: $session->branch_id,
                customer: $customer,
                payload: [
                    'invoice_date' => $payload['sale_date'],
                    'due_date' => $payload['sale_date'],
                    'warehouse_id' => $session->warehouse_id,
                    'notes' => $payload['notes'] ?? 'Vente comptoir',
                    'sale_channel' => 'pos',
                    'pos_session_id' => $session->id,
                    'pos_sync_key' => $syncKey,
                    'discount_type' => $payload['discount_type'] ?? 'none',
                    'discount_value' => $payload['discount_value'] ?? 0,
                ],
                items: $items,
                user: $user,
            );

            $paymentPlan = $this->normalizeSalePayments($session, $payload, $paymentsInput, (float) $invoice->total);

            $invoice->update([
                'pos_cash_received' => $paymentPlan['cash_received_amount'],
                'pos_change_due' => $paymentPlan['change_due'],
            ]);

            $payments = collect();
            foreach ($paymentPlan['payments'] as $index => $paymentLine) {
                $payments->push($this->paymentService->recordCustomerReceipt(
                    companyId: $session->company_id,
                    branchId: $session->branch_id,
                    invoice: $invoice,
                    cashAccount: $paymentLine['cash_account'],
                    payload: [
                        'payment_date' => $payload['sale_date'],
                        'amount' => $paymentLine['amount'],
                        'method' => $paymentLine['method'],
                        'reference' => $this->paymentReferenceForLine($payload['reference'] ?? null, $session->session_number, $index),
                        'notes' => trim(($payload['payment_notes'] ?? 'Encaissement ticket POS '.$invoice->invoice_number).($paymentLine['label'] ? ' · '.$paymentLine['label'] : '')),
                        'pos_session_id' => $session->id,
                    ],
                    user: $user,
                ));
                $invoice->refresh();
            }

            $this->preparationService->ensureTicketsForInvoice($invoice, $user->id);

            return [
                'session' => $session->fresh(['cashAccount', 'warehouse']),
                'invoice' => $invoice->fresh(['customer', 'branch', 'warehouse', 'posSession', 'items.product', 'items.posReturnItems', 'paymentAllocations.payment.cashAccount', 'posReturns.items']),
                'payments' => $payments->map(fn (Payment $payment) => $payment->fresh(['cashAccount', 'partner', 'posSession']))->values(),
                'already_processed' => false,
            ];
        });
    }

    public function draftsForSession(PosSession $session, ?int $userId = null): EloquentCollection
    {
        return PosDraft::query()
            ->with(['customer', 'creator', 'updater'])
            ->where('company_id', $session->company_id)
            ->where('branch_id', $session->branch_id)
            ->where('pos_session_id', $session->id)
            ->when($userId, fn (Builder $query) => $query->where('created_by', $userId))
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id')
            ->get();
    }

    public function saveDraft(PosSession $session, array $payload, array $itemsInput, array $paymentsInput, User $user, ?PosDraft $draft = null): PosDraft
    {
        return DB::transaction(function () use ($session, $payload, $itemsInput, $paymentsInput, $user, $draft) {
            $session = PosSession::query()
                ->with(['cashAccount', 'warehouse'])
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $session->isOpen()) {
                throw ValidationException::withMessages([
                    'session' => 'La session de caisse doit etre ouverte pour mettre la commande en attente.',
                ]);
            }

            if ($draft && ($draft->company_id !== $session->company_id || $draft->branch_id !== $session->branch_id || $draft->pos_session_id !== $session->id)) {
                throw ValidationException::withMessages([
                    'draft' => 'Ce brouillon POS ne correspond pas a la session courante.',
                ]);
            }

            $normalizedItems = $this->salesInvoiceService->normalizeItems($session->company_id, $itemsInput, null, $user);
            if ($normalizedItems instanceof Collection) {
                $normalizedItems = $normalizedItems->values()->all();
            }
            if ($normalizedItems === []) {
                throw ValidationException::withMessages([
                    'items' => 'Ajoute au moins un article avant de mettre la commande en attente.',
                ]);
            }

            $customerId = ! empty($payload['customer_id'])
                ? (int) Partner::query()
                    ->customers()
                    ->where('company_id', $session->company_id)
                    ->findOrFail((int) $payload['customer_id'])
                    ->id
                : null;

            $discountType = in_array($payload['discount_type'] ?? 'none', ['none', 'fixed', 'percent'], true)
                ? ($payload['discount_type'] ?? 'none')
                : 'none';
            $discountValue = round((float) ($payload['discount_value'] ?? 0), 2);
            $totals = $this->draftTotals($normalizedItems, $discountType, $discountValue);
            $payments = $this->normalizeDraftPayments($session, $payload, $paymentsInput, $totals['total']);

            $attributes = [
                'company_id' => $session->company_id,
                'branch_id' => $session->branch_id,
                'pos_session_id' => $session->id,
                'customer_id' => $customerId,
                'label' => $this->normalizeDraftLabel($session, $payload['label'] ?? null),
                'sale_date' => $payload['sale_date'] ?? now()->toDateString(),
                'method' => $payload['method'] ?? 'cash',
                'reference' => $payload['reference'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'items' => $normalizedItems,
                'payments' => $payments,
                'items_count' => count($normalizedItems),
                'total' => $totals['total'],
                'cash_received_amount' => round((float) ($payload['cash_received_amount'] ?? 0), 2),
                'last_activity_at' => now(),
                'updated_by' => $user->id,
            ];

            if ($draft) {
                $draft->fill($attributes);
                $draft->save();
            } else {
                $draft = PosDraft::query()->create($attributes + [
                    'created_by' => $user->id,
                ]);
            }

            return $draft->fresh(['customer', 'creator', 'updater']);
        });
    }

    public function deleteDraft(PosSession $session, PosDraft $draft): void
    {
        if ($draft->company_id !== $session->company_id || $draft->branch_id !== $session->branch_id || $draft->pos_session_id !== $session->id) {
            throw ValidationException::withMessages([
                'draft' => 'Ce brouillon POS ne correspond pas a la session courante.',
            ]);
        }

        $draft->delete();
    }

    private function normalizeDraftLabel(PosSession $session, mixed $label): string
    {
        $clean = trim((string) ($label ?? ''));
        if ($clean !== '') {
            return mb_substr($clean, 0, 80);
        }

        $next = PosDraft::query()
            ->where('pos_session_id', $session->id)
            ->pluck('label')
            ->map(function (string $existing): int {
                preg_match('/(\\d+)$/', $existing, $matches);

                return isset($matches[1]) ? (int) $matches[1] : 0;
            })
            ->max();

        return 'Commande '.(((int) $next) + 1);
    }

    private function draftLineSubtotal(array $item): float
    {
        return round(max((float) ($item['qty'] ?? 0), 0) * max((float) ($item['unit_price'] ?? 0), 0), 2);
    }

    private function draftLineDiscount(array $item): float
    {
        $subtotal = $this->draftLineSubtotal($item);
        $type = $item['discount_type'] ?? 'none';
        $value = max((float) ($item['discount_value'] ?? 0), 0);

        return match ($type) {
            'fixed' => round(min($subtotal, $value), 2),
            'percent' => round($subtotal * min($value, 100) / 100, 2),
            default => 0.0,
        };
    }

    private function draftTicketDiscount(float $base, string $discountType, float $discountValue): float
    {
        $value = max($discountValue, 0);

        return match ($discountType) {
            'fixed' => round(min($base, $value), 2),
            'percent' => round($base * min($value, 100) / 100, 2),
            default => 0.0,
        };
    }

    private function draftTotals(array $items, string $discountType, float $discountValue): array
    {
        $subtotal = round(collect($items)->sum(fn (array $item) => $this->draftLineSubtotal($item)), 2);
        $lineDiscounts = round(collect($items)->sum(fn (array $item) => $this->draftLineDiscount($item)), 2);
        $base = max($subtotal - $lineDiscounts, 0);
        $ticketDiscount = $this->draftTicketDiscount($base, $discountType, $discountValue);

        return [
            'subtotal' => $subtotal,
            'line_discounts' => $lineDiscounts,
            'ticket_discount' => $ticketDiscount,
            'total' => round(max($base - $ticketDiscount, 0), 2),
        ];
    }

    private function normalizeDraftPayments(PosSession $session, array $payload, array $paymentsInput, float $draftTotal): array
    {
        $allowedMethods = $this->runtimeMethodOptions($session->company_id, $session->branch_id);

        $payments = collect($paymentsInput)
            ->map(function (array $payment) use ($session, $allowedMethods): ?array {
                $method = $payment['method'] ?? null;
                if (! $method || ! array_key_exists($method, $allowedMethods)) {
                    return null;
                }

                $cashAccount = $this->resolveCashAccountForMethod(
                    session: $session,
                    method: $method,
                    cashAccountId: filled($payment['cash_account_id'] ?? null) ? (int) $payment['cash_account_id'] : null,
                    strict: false,
                );

                return [
                    'method' => $method,
                    'amount' => round(max((float) ($payment['amount'] ?? 0), 0), 2),
                    'cash_account_id' => $cashAccount?->id,
                    'label' => filled($payment['label'] ?? null) ? trim((string) $payment['label']) : null,
                ];
            })
            ->filter()
            ->values();

        if ($payments->isEmpty()) {
            $fallbackMethod = $payload['method'] ?? 'cash';
            if (! array_key_exists($fallbackMethod, $allowedMethods)) {
                throw ValidationException::withMessages([
                    'method' => 'Le mode de paiement choisi n est pas actif sur ce profil de caisse.',
                ]);
            }
            $cashAccount = $this->resolveCashAccountForMethod($session, $fallbackMethod, null, false);

            return [[
                'method' => $fallbackMethod,
                'amount' => round($draftTotal, 2),
                'cash_account_id' => $cashAccount?->id,
                'label' => null,
            ]];
        }

        return $payments->all();
    }

    private function normalizeSalePayments(PosSession $session, array $payload, array $paymentsInput, float $invoiceTotal): array
    {
        $allowedMethods = $this->runtimeMethodOptions($session->company_id, $session->branch_id);
        $hasExplicitPayments = collect($paymentsInput)->isNotEmpty();

        $payments = collect($paymentsInput)
            ->map(function (array $payment) use ($session, $allowedMethods): ?array {
                $method = $payment['method'] ?? null;
                if (! $method || ! array_key_exists($method, $allowedMethods)) {
                    return null;
                }

                $amount = round((float) ($payment['amount'] ?? 0), 2);
                if ($amount <= 0) {
                    return null;
                }

                return [
                    'method' => $method,
                    'amount' => $amount,
                    'cash_account' => $this->resolveCashAccountForMethod(
                        session: $session,
                        method: $method,
                        cashAccountId: filled($payment['cash_account_id'] ?? null) ? (int) $payment['cash_account_id'] : null,
                    ),
                    'label' => filled($payment['label'] ?? null) ? trim((string) $payment['label']) : null,
                ];
            })
            ->filter()
            ->values();

        if ($payments->isEmpty()) {
            if ($hasExplicitPayments) {
                throw ValidationException::withMessages([
                    'payments' => 'Saisis au moins un reglement positif pour ce ticket.',
                ]);
            }

            $method = $payload['method'] ?? 'cash';
            if (! array_key_exists($method, $allowedMethods)) {
                throw ValidationException::withMessages([
                    'method' => 'Le mode de paiement choisi n est pas actif sur ce profil de caisse.',
                ]);
            }
            $payments = collect([[
                'method' => $method,
                'amount' => round($invoiceTotal, 2),
                'cash_account' => $this->resolveCashAccountForMethod($session, $method),
                'label' => null,
            ]]);
        }

        $allocatedTotal = round((float) $payments->sum('amount'), 2);
        if ($allocatedTotal - round($invoiceTotal, 2) > 0.01) {
            throw ValidationException::withMessages([
                'payments' => 'Le total des reglements ne doit pas depasser le ticket.',
            ]);
        }

        $cashAllocated = round((float) $payments->where('method', 'cash')->sum('amount'), 2);
        $cashReceived = round((float) ($payload['cash_received_amount'] ?? 0), 2);

        if ($cashAllocated > 0) {
            if ($cashReceived < $cashAllocated) {
                throw ValidationException::withMessages([
                    'cash_received_amount' => 'Le montant recu en especes doit couvrir la part cash du ticket.',
                ]);
            }
        } else {
            $cashReceived = 0;
        }

        return [
            'payments' => $payments->all(),
            'cash_received_amount' => $cashReceived,
            'change_due' => round(max($cashReceived - $cashAllocated, 0), 2),
        ];
    }

    private function resolveCashAccountForMethod(PosSession $session, string $method, ?int $cashAccountId = null, bool $strict = true): ?CashAccount
    {
        $session->loadMissing('cashAccount');

        if ($method === 'cash') {
            return $session->cashAccount;
        }

        $query = CashAccount::query()
            ->where('company_id', $session->company_id)
            ->where('is_active', true)
            ->where(function ($builder) use ($session): void {
                $builder->where('branch_id', $session->branch_id)->orWhereNull('branch_id');
            });

        if ($cashAccountId) {
            $account = (clone $query)->find($cashAccountId);
            if (! $account) {
                if ($strict) {
                    throw ValidationException::withMessages([
                        'payments' => 'Le compte de tresorerie choisi est introuvable pour cette caisse.',
                    ]);
                }

                return null;
            }

            if (! $this->cashAccountMatchesMethod($account, $method)) {
                if ($strict) {
                    throw ValidationException::withMessages([
                        'payments' => 'Le compte de tresorerie choisi ne correspond pas au mode de paiement selectionne.',
                    ]);
                }

                return null;
            }

            return $account;
        }

        $account = match ($method) {
            'wave' => (clone $query)->where('type', 'mobile_money')->where('name', 'like', '%Wave%')->first(),
            'orange_money' => (clone $query)->where('type', 'mobile_money')->where('name', 'like', '%Orange%')->first(),
            'moov_money' => (clone $query)->where('type', 'mobile_money')->where('name', 'like', '%Moov%')->first(),
            'mobile_money' => (clone $query)->where('type', 'mobile_money')->first(),
            'bank_transfer', 'cheque' => (clone $query)->where('type', 'bank')->first(),
            default => $session->cashAccount,
        };

        if (! $account && $strict) {
            throw ValidationException::withMessages([
                'payments' => 'Aucun compte de tresorerie actif ne correspond a ce mode de paiement.',
            ]);
        }

        return $account;
    }

    private function cashAccountMatchesMethod(CashAccount $account, string $method): bool
    {
        return match ($method) {
            'wave' => $account->type === 'mobile_money' && str($account->name)->lower()->contains('wave'),
            'orange_money' => $account->type === 'mobile_money' && str($account->name)->lower()->contains('orange'),
            'moov_money' => $account->type === 'mobile_money' && str($account->name)->lower()->contains('moov'),
            'mobile_money' => $account->type === 'mobile_money',
            'bank_transfer', 'cheque' => $account->type === 'bank',
            'cash' => $account->type === 'cash',
            default => true,
        };
    }

    private function paymentReferenceForLine(?string $reference, string $fallback, int $index): string
    {
        $base = trim((string) ($reference ?: $fallback));

        return $index === 0 ? $base : $base.'-'.($index + 1);
    }

    public function processReturn(PosSession $session, SalesInvoice $invoice, array $payload, array $itemsInput, array $exchangeItemsInput, User $user): array
    {
        return DB::transaction(function () use ($session, $invoice, $payload, $itemsInput, $exchangeItemsInput, $user) {
            $session = PosSession::query()->with(['cashAccount', 'warehouse'])->whereKey($session->id)->lockForUpdate()->firstOrFail();
            $invoice = SalesInvoice::query()->with(['customer', 'posSession', 'items.product', 'items.posReturnItems', 'posReturns.items'])->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if (! $session->isOpen()) {
                throw ValidationException::withMessages([
                    'session' => 'La session de caisse doit etre ouverte pour traiter un retour ou une annulation.',
                ]);
            }

            if ($invoice->sale_channel !== 'pos' || $invoice->status !== 'validated') {
                throw ValidationException::withMessages([
                    'sale' => 'Seuls les tickets POS valides peuvent etre retournes.',
                ]);
            }

            if ($invoice->posSession && ! $invoice->posSession->isOpen()) {
                throw ValidationException::withMessages([
                    'sale' => 'Caisse fermee : impossible de modifier, annuler ou retourner ce ticket POS.',
                ]);
            }

            $returnableItems = $this->returnableItems($invoice)->keyBy('sales_invoice_item_id');
            $selectedItems = collect($itemsInput)
                ->map(fn (array $item) => [
                    'sales_invoice_item_id' => (int) ($item['sales_invoice_item_id'] ?? 0),
                    'qty' => round((float) ($item['qty'] ?? 0), 3),
                ])
                ->filter(fn (array $item) => $item['sales_invoice_item_id'] > 0 && $item['qty'] > 0)
                ->values();

            if ($selectedItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Renseigne au moins une quantite a rembourser.',
                ]);
            }

            $lines = $selectedItems->map(function (array $item) use ($returnableItems) {
                $context = $returnableItems->get($item['sales_invoice_item_id']);

                if (! $context) {
                    throw ValidationException::withMessages([
                        'items' => 'Une ligne de retour ne correspond pas au ticket selectionne.',
                    ]);
                }

                if ($item['qty'] > $context['remaining_qty']) {
                    throw ValidationException::withMessages([
                        'items' => 'La quantite retournee depasse le solde encore remboursable du ticket.',
                    ]);
                }

                $lineTotal = $context['remaining_qty'] > 0
                    ? round(($item['qty'] / $context['remaining_qty']) * $context['remaining_total'], 2)
                    : 0;

                return [
                    'invoice_item' => $context['invoice_item'],
                    'product' => $context['invoice_item']->product,
                    'description' => $context['invoice_item']->description,
                    'qty' => $item['qty'],
                    'unit_price' => $item['qty'] > 0 ? round($lineTotal / $item['qty'], 2) : 0,
                    'line_total' => $lineTotal,
                ];
            });

            $total = round((float) $lines->sum('line_total'), 2);
            if ($total <= 0) {
                throw ValidationException::withMessages([
                    'items' => 'Le montant du retour doit etre strictement positif.',
                ]);
            }

            $this->ensureSequence($session->company_id, 'pos_return', 'RET-{BRANCH}-{YEAR}-');

            $return = PosReturn::query()->create([
                'company_id' => $session->company_id,
                'branch_id' => $session->branch_id,
                'pos_session_id' => $session->id,
                'sales_invoice_id' => $invoice->id,
                'cash_account_id' => $session->cash_account_id,
                'return_number' => $this->documentNumberService->nextNumber(
                    companyId: $session->company_id,
                    documentType: 'pos_return',
                    branchId: $session->branch_id,
                    date: $payload['return_date'],
                ),
                'return_date' => $payload['return_date'],
                'status' => 'processed',
                'method' => $payload['method'],
                'total' => $total,
                'notes' => $payload['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            foreach ($lines as $line) {
                /** @var SalesInvoiceItem $invoiceItem */
                $invoiceItem = $line['invoice_item'];
                /** @var Product|null $product */
                $product = $line['product'];

                $return->items()->create([
                    'sales_invoice_item_id' => $invoiceItem->id,
                    'product_id' => $invoiceItem->product_id,
                    'description' => $line['description'],
                    'qty' => $line['qty'],
                    'unit_price' => $line['unit_price'],
                    'line_total' => $line['line_total'],
                ]);

                if ($product && $product->type === 'stockable') {
                    $this->stockService->recordAdjustment(
                        product: $product,
                        companyId: $session->company_id,
                        branchId: $session->branch_id,
                        direction: 'in',
                        quantity: (float) $line['qty'],
                        unitCost: (float) $product->purchase_price,
                        reason: 'Retour ticket POS',
                        notes: 'Retour '.$return->return_number,
                        user: $user,
                        movementDate: $payload['return_date'],
                        warehouseId: $session->warehouse_id,
                        referenceType: PosReturn::class,
                        referenceId: $return->id,
                    );
                }
            }

            $refundPayment = $this->paymentService->recordPosRefund(
                companyId: $session->company_id,
                branchId: $session->branch_id,
                invoice: $invoice,
                cashAccount: $session->cashAccount,
                return: $return,
                payload: [
                    'payment_date' => $payload['return_date'],
                    'amount' => $total,
                    'method' => $payload['method'],
                    'reference' => $payload['reference'] ?? $return->return_number,
                    'notes' => $payload['notes'] ?? 'Retour POS '.$invoice->invoice_number,
                    'pos_session_id' => $session->id,
                ],
                user: $user,
            );

            $exchangeItems = collect($exchangeItemsInput)
                ->filter(fn (array $item) => filled($item['product_id'] ?? null))
                ->values();

            $exchangeInvoice = null;
            $exchangePayment = null;

            if ($exchangeItems->isNotEmpty()) {
                $normalizedExchangeItems = $this->salesInvoiceService->normalizeItems($session->company_id, $exchangeItems->all(), null, $user);
                $this->salesInvoiceService->assertCreatable($session->company_id, $session->branch_id, $normalizedExchangeItems, $session->warehouse_id);

                $exchangeInvoice = $this->salesInvoiceService->createValidated(
                    companyId: $session->company_id,
                    branchId: $session->branch_id,
                    customer: $invoice->customer,
                    payload: [
                        'invoice_date' => $payload['return_date'],
                        'due_date' => $payload['return_date'],
                        'warehouse_id' => $session->warehouse_id,
                        'notes' => 'Echange ticket '.$invoice->invoice_number.' / '.$return->return_number,
                        'sale_channel' => 'pos',
                        'pos_session_id' => $session->id,
                        'discount_type' => 'none',
                        'discount_value' => 0,
                    ],
                    items: $normalizedExchangeItems,
                    user: $user,
                );

                $exchangePayment = $this->paymentService->recordCustomerReceipt(
                    companyId: $session->company_id,
                    branchId: $session->branch_id,
                    invoice: $exchangeInvoice,
                    cashAccount: $session->cashAccount,
                    payload: [
                        'payment_date' => $payload['return_date'],
                        'amount' => (float) $exchangeInvoice->total,
                        'method' => $payload['method'],
                        'reference' => ($payload['reference'] ?? $return->return_number).'-ECH',
                        'notes' => 'Encaissement echange POS '.$exchangeInvoice->invoice_number,
                        'pos_session_id' => $session->id,
                    ],
                    user: $user,
                );

                $return->update([
                    'exchange_sales_invoice_id' => $exchangeInvoice->id,
                ]);
            }

            return [
                'session' => $session->fresh(['cashAccount', 'warehouse', 'payments', 'returns']),
                'return' => $return->fresh(['invoice.customer', 'exchangeInvoice.customer', 'session', 'payment.cashAccount', 'items.product', 'creator']),
                'payment' => $refundPayment,
                'invoice' => $invoice->fresh(['customer', 'items.product', 'items.posReturnItems', 'posReturns.items', 'paymentAllocations.payment.cashAccount']),
                'exchange_invoice' => $exchangeInvoice?->fresh(['customer', 'items.product', 'paymentAllocations.payment.cashAccount']),
                'exchange_payment' => $exchangePayment?->fresh(['cashAccount', 'partner', 'posSession']),
            ];
        });
    }

    public function returnableItems(SalesInvoice $invoice): Collection
    {
        $invoice->loadMissing(['items.product', 'items.posReturnItems', 'posReturns.items']);

        $returnedByItem = PosReturnItem::query()
            ->select('sales_invoice_item_id')
            ->selectRaw('COALESCE(SUM(qty), 0) as returned_qty')
            ->selectRaw('COALESCE(SUM(line_total), 0) as returned_total')
            ->whereHas('posReturn', fn (Builder $query) => $query->where('sales_invoice_id', $invoice->id)->where('status', 'processed'))
            ->groupBy('sales_invoice_item_id')
            ->get()
            ->keyBy('sales_invoice_item_id');

        $globalDiscountAllocations = $this->allocateGlobalDiscounts($invoice);

        return $invoice->items->values()->map(function (SalesInvoiceItem $item) use ($returnedByItem, $globalDiscountAllocations) {
            $returned = $returnedByItem->get($item->id);
            $returnedQty = (float) ($returned?->returned_qty ?? 0);
            $returnedTotal = (float) ($returned?->returned_total ?? 0);
            $remainingQty = max(round((float) $item->qty - $returnedQty, 3), 0);
            $globalDiscountAllocation = (float) ($globalDiscountAllocations[$item->id] ?? 0);
            $effectiveLineTotal = max(round((float) $item->line_total - $globalDiscountAllocation, 2), 0);
            $remainingTotal = max(round($effectiveLineTotal - $returnedTotal, 2), 0);

            return [
                'invoice_item' => $item,
                'sales_invoice_item_id' => $item->id,
                'returned_qty' => $returnedQty,
                'remaining_qty' => $remainingQty,
                'returned_total' => $returnedTotal,
                'remaining_total' => $remainingTotal,
                'line_total_remaining' => $remainingTotal,
                'refund_unit_price' => $remainingQty > 0 ? round($remainingTotal / $remainingQty, 2) : 0,
            ];
        });
    }

    public function summary(PosSession $session): array
    {
        $session->loadMissing(['salesInvoices.posReturns', 'payments', 'returns']);

        $salesCount = (int) $session->salesInvoices->count();
        $grossSalesTotal = (float) $session->salesInvoices->sum('subtotal');
        $discountTotal = (float) $session->salesInvoices->sum('discount_total');
        $salesTotal = (float) $session->salesInvoices->sum('total');
        $returnCount = (int) $session->returns->count();
        $returnTotal = (float) $session->returns->sum('total');
        $paymentsIn = (float) $session->payments->where('direction', 'in')->sum('amount');
        $paymentsOut = (float) $session->payments->where('direction', 'out')->sum('amount');
        $itemsCount = (int) SalesInvoice::query()
            ->where('pos_session_id', $session->id)
            ->join('sales_invoice_items', 'sales_invoice_items.sales_invoice_id', '=', 'sales_invoices.id')
            ->sum('sales_invoice_items.qty');
        $returnedItemsCount = (int) PosReturnItem::query()
            ->whereHas('posReturn', fn (Builder $query) => $query->where('pos_session_id', $session->id))
            ->sum('qty');
        $expectedBreakdown = $session->status === 'closed' && is_array($session->expected_breakdown)
            ? $this->normalizeMethodBreakdown($session->expected_breakdown)
            : $this->expectedMethodBreakdown($session);
        $countedBreakdown = $session->status === 'closed' && is_array($session->counted_breakdown)
            ? $this->normalizeMethodBreakdown($session->counted_breakdown)
            : $this->zeroMethodBreakdown();
        $varianceBreakdown = $session->status === 'closed' && is_array($session->variance_breakdown)
            ? $this->normalizeMethodBreakdown($session->variance_breakdown)
            : $this->differenceBreakdown($countedBreakdown, $expectedBreakdown);
        $expectedAmount = round(array_sum($expectedBreakdown), 2);

        return [
            'sales_count' => $salesCount,
            'gross_sales_total' => $grossSalesTotal,
            'discount_total' => $discountTotal,
            'sales_total' => $salesTotal,
            'return_count' => $returnCount,
            'return_total' => $returnTotal,
            'net_sales_total' => round($salesTotal - $returnTotal, 2),
            'paid_total' => $paymentsIn,
            'refund_total' => $paymentsOut,
            'net_cash' => round($paymentsIn - $paymentsOut, 2),
            'items_count' => $itemsCount,
            'returned_items_count' => $returnedItemsCount,
            'expected_amount' => $session->status === 'closed'
                ? (float) ($session->expected_amount ?? $expectedAmount)
                : $expectedAmount,
            'expected_breakdown' => $expectedBreakdown,
            'counted_breakdown' => $countedBreakdown,
            'variance_breakdown' => $varianceBreakdown,
            'variance_notes' => $session->status === 'closed' && is_array($session->variance_notes)
                ? $session->variance_notes
                : $this->emptyVarianceNotes(),
        ];
    }

    public function recentInvoices(PosSession $session, int $limit = 25): EloquentCollection
    {
        return $session->salesInvoices()
            ->with([
                'customer',
                'creator',
                'items.product',
                'paymentAllocations.payment.cashAccount',
                'posReturns.items.product',
                'posReturns.payment.cashAccount',
                'posReturns.exchangeInvoice',
                'posReturns.creator',
            ])
            ->latest('invoice_date')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function sessionTicketRows(PosSession $session, int $limit = 25): Collection
    {
        $methods = $this->methodOptions();

        return $this->recentInvoices($session, $limit)
            ->map(function (SalesInvoice $invoice) use ($session, $methods): array {
                $payments = $invoice->paymentAllocations
                    ->pluck('payment')
                    ->filter(fn ($payment) => $payment instanceof Payment)
                    ->unique('id')
                    ->sortBy('payment_date')
                    ->values();
                $returns = $invoice->posReturns
                    ->where('status', 'processed')
                    ->values();
                $returnedAmount = round((float) $returns->sum('total'), 2);
                $status = $this->ticketStatus($invoice, $returnedAmount);
                $ticketReference = $payments->first()?->reference
                    ?: $invoice->pos_sync_key
                    ?: $invoice->invoice_number;
                $productText = $invoice->items
                    ->map(fn (SalesInvoiceItem $item) => trim((string) ($item->description ?: $item->product?->name)))
                    ->filter()
                    ->implode(' ');
                $searchText = collect([
                    $invoice->invoice_number,
                    $ticketReference,
                    $invoice->customer?->name,
                    $invoice->invoice_date?->format('d/m/Y'),
                    $invoice->created_at?->format('H:i'),
                    $productText,
                    $status['label'],
                ])->filter()->implode(' ');

                return [
                    'id' => $invoice->id,
                    'date' => $invoice->invoice_date?->format('d/m/Y') ?? $invoice->created_at?->format('d/m/Y'),
                    'time' => $invoice->created_at?->format('H:i:s') ?? $invoice->invoice_date?->format('H:i:s'),
                    'invoice_number' => $invoice->invoice_number,
                    'ticket_reference' => $ticketReference,
                    'customer' => $invoice->customer?->name ?? 'Client comptoir',
                    'cashier' => $invoice->creator?->name ?? $session->opener?->name ?? 'Operateur',
                    'amount' => round((float) $invoice->total, 2),
                    'amount_paid' => round((float) $invoice->amount_paid, 2),
                    'balance_due' => round((float) $invoice->balance_due, 2),
                    'discount_total' => round((float) $invoice->discount_total, 2),
                    'returned_amount' => $returnedAmount,
                    'status' => $status,
                    'is_locked' => ! $session->isOpen(),
                    'can_refund' => $session->isOpen() && $status['key'] !== 'cancelled',
                    'can_pay' => $session->isOpen() && (float) $invoice->balance_due > 0.009,
                    'receipt_url' => route('pos.receipt', $invoice),
                    'thermal_url' => route('pos.receipt.thermal', $invoice),
                    'return_url' => route('pos.returns.create', ['sale' => $invoice, 'session' => $session->id]),
                    'payment_url' => route('payments.create', ['type' => 'customer_receipt', 'invoice' => $invoice->id, 'amount' => (float) $invoice->balance_due]),
                    'search' => str($searchText)->lower()->ascii()->value(),
                    'items' => $invoice->items->map(fn (SalesInvoiceItem $item): array => [
                        'description' => $item->description ?: $item->product?->name ?? 'Article',
                        'code' => $item->product?->barcode ?: $item->product?->sku,
                        'qty' => round((float) $item->qty, 3),
                        'unit_price' => round((float) $item->unit_price, 2),
                        'discount_total' => round((float) $item->discount_total, 2),
                        'line_total' => round((float) $item->line_total, 2),
                    ])->values()->all(),
                    'payments' => $payments->map(fn (Payment $payment): array => [
                        'date' => $payment->payment_date?->format('d/m/Y') ?? $payment->created_at?->format('d/m/Y'),
                        'method' => $methods[$payment->method] ?? $payment->method,
                        'amount' => round((float) $payment->amount, 2),
                        'direction' => $payment->direction,
                        'reference' => $payment->reference,
                        'cash_account' => $payment->cashAccount?->name,
                    ])->values()->all(),
                    'returns' => $returns->map(fn (PosReturn $return): array => [
                        'number' => $return->return_number,
                        'date' => $return->return_date?->format('d/m/Y'),
                        'amount' => round((float) $return->total, 2),
                        'method' => $methods[$return->method] ?? $return->method,
                        'reason' => $return->notes,
                        'cashier' => $return->creator?->name,
                        'items' => $return->items->map(fn (PosReturnItem $item): array => [
                            'description' => $item->description ?: $item->product?->name ?? 'Article',
                            'qty' => round((float) $item->qty, 3),
                            'line_total' => round((float) $item->line_total, 2),
                        ])->values()->all(),
                    ])->values()->all(),
                    'history' => $this->ticketHistory($invoice, $payments, $returns),
                ];
            })
            ->values();
    }

    private function ticketStatus(SalesInvoice $invoice, float $returnedAmount): array
    {
        if ($invoice->status === 'cancelled') {
            return ['key' => 'cancelled', 'label' => 'Annule', 'tone' => 'danger'];
        }

        if ($returnedAmount > 0) {
            return ['key' => 'refunded', 'label' => $returnedAmount >= (float) $invoice->total ? 'Rembourse' : 'Rembourse partiel', 'tone' => 'purple'];
        }

        if ($invoice->payment_status === 'paid' && (float) $invoice->balance_due <= 0.009) {
            return ['key' => 'paid', 'label' => 'Paye', 'tone' => 'success'];
        }

        return ['key' => 'pending', 'label' => 'En attente', 'tone' => 'warning'];
    }

    private function ticketHistory(SalesInvoice $invoice, Collection $payments, Collection $returns): array
    {
        $events = collect([
            [
                'date' => $invoice->created_at?->format('d/m/Y H:i'),
                'label' => 'Ticket cree',
                'detail' => $invoice->creator?->name ?? 'Operateur',
            ],
        ]);

        foreach ($payments as $payment) {
            if (! $payment instanceof Payment) {
                continue;
            }

            $events->push([
                'date' => $payment->created_at?->format('d/m/Y H:i'),
                'label' => $payment->direction === 'out' ? 'Remboursement' : 'Paiement',
                'detail' => number_format((float) $payment->amount, 0, ',', ' ').' XOF',
            ]);
        }

        foreach ($returns as $return) {
            if (! $return instanceof PosReturn) {
                continue;
            }

            $events->push([
                'date' => $return->created_at?->format('d/m/Y H:i'),
                'label' => 'Retour '.$return->return_number,
                'detail' => number_format((float) $return->total, 0, ',', ' ').' XOF'.($return->notes ? ' · '.$return->notes : ''),
            ]);
        }

        return $events
            ->filter(fn (array $event) => filled($event['label']))
            ->sortByDesc('date')
            ->values()
            ->all();
    }

    public function dailyReport(int $companyId, int $branchId, array $filters): array
    {
        $date = $filters['date'] ?? now()->toDateString();

        $sessionIds = $this->dailyReportSessionIds($companyId, $branchId, $filters, $date);

        $sessionsQuery = PosSession::query()
            ->with(['cashAccount', 'warehouse', 'opener', 'closer'])
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->when($filters['warehouse_id'] ?? null, fn (Builder $query, $warehouseId) => $query->where('warehouse_id', $warehouseId))
            ->when($filters['cash_account_id'] ?? null, fn (Builder $query, $cashAccountId) => $query->where('cash_account_id', $cashAccountId))
            ->when(
                $sessionIds->isNotEmpty(),
                fn (Builder $query) => $query->whereKey($sessionIds->all()),
                fn (Builder $query) => $query->whereRaw('1 = 0'),
            );

        $sessions = $sessionsQuery->latest('opened_at')->get();
        $sessionIds = $sessions->pluck('id');

        $salesQuery = SalesInvoice::query()
            ->where('company_id', $companyId)
            ->where('sale_channel', 'pos')
            ->whereIn('pos_session_id', $sessionIds)
            ->whereDate('invoice_date', $date);

        $returnsQuery = PosReturn::query()
            ->where('company_id', $companyId)
            ->whereIn('pos_session_id', $sessionIds)
            ->whereDate('return_date', $date);

        $paymentsQuery = Payment::query()
            ->where('company_id', $companyId)
            ->whereIn('pos_session_id', $sessionIds)
            ->whereDate('payment_date', $date);

        $grossSales = (float) (clone $salesQuery)->sum('subtotal');
        $discountsTotal = (float) (clone $salesQuery)->sum('discount_total');
        $salesAfterDiscount = (float) (clone $salesQuery)->sum('total');
        $returnsTotal = (float) (clone $returnsQuery)->sum('total');
        $incoming = (float) (clone $paymentsQuery)->where('direction', 'in')->sum('amount');
        $outgoing = (float) (clone $paymentsQuery)->where('direction', 'out')->sum('amount');

        $methodBreakdown = [];
        foreach ($this->methodOptions() as $method => $label) {
            $methodIn = (float) (clone $paymentsQuery)->where('direction', 'in')->where('method', $method)->sum('amount');
            $methodOut = (float) (clone $paymentsQuery)->where('direction', 'out')->where('method', $method)->sum('amount');
            $methodBreakdown[$method] = [
                'label' => $label,
                'incoming' => $methodIn,
                'outgoing' => $methodOut,
                'net' => round($methodIn - $methodOut, 2),
            ];
        }

        $topProducts = DB::table('sales_invoice_items')
            ->join('sales_invoices', 'sales_invoices.id', '=', 'sales_invoice_items.sales_invoice_id')
            ->join('products', 'products.id', '=', 'sales_invoice_items.product_id')
            ->where('sales_invoices.company_id', $companyId)
            ->where('sales_invoices.sale_channel', 'pos')
            ->whereIn('sales_invoices.pos_session_id', $sessionIds)
            ->whereDate('sales_invoices.invoice_date', $date)
            ->selectRaw('products.id, products.name, products.sku, products.image_path, COALESCE(products.barcode, products.sku) as scan_code, SUM(sales_invoice_items.qty) as qty, SUM(sales_invoice_items.line_total) as amount')
            ->groupBy('products.id', 'products.name', 'products.sku', 'products.barcode', 'products.image_path')
            ->orderByDesc('qty')
            ->limit(8)
            ->get()
            ->map(fn ($product) => tap($product, function ($product): void {
                $product->image_url = $product->image_path ? url(route('products.media.show', ['path' => $product->image_path], false)) : null;
            }));

        $topReturns = DB::table('pos_return_items')
            ->join('pos_returns', 'pos_returns.id', '=', 'pos_return_items.pos_return_id')
            ->join('products', 'products.id', '=', 'pos_return_items.product_id')
            ->where('pos_returns.company_id', $companyId)
            ->whereIn('pos_returns.pos_session_id', $sessionIds)
            ->whereDate('pos_returns.return_date', $date)
            ->selectRaw('products.id, products.name, products.sku, products.image_path, SUM(pos_return_items.qty) as qty, SUM(pos_return_items.line_total) as amount')
            ->groupBy('products.id', 'products.name', 'products.sku', 'products.image_path')
            ->orderByDesc('qty')
            ->limit(8)
            ->get()
            ->map(fn ($product) => tap($product, function ($product): void {
                $product->image_url = $product->image_path ? url(route('products.media.show', ['path' => $product->image_path], false)) : null;
            }));
        $payments = $paymentsQuery
            ->with(['reconciliationItem'])
            ->get();

        return [
            'date' => $date,
            'filters' => $filters,
            'sessions' => $sessions,
            'sales_count' => (int) (clone $salesQuery)->count(),
            'gross_sales' => $grossSales,
            'discounts_total' => $discountsTotal,
            'sales_after_discount' => $salesAfterDiscount,
            'returns_count' => (int) (clone $returnsQuery)->count(),
            'returns_total' => $returnsTotal,
            'net_sales' => round($salesAfterDiscount - $returnsTotal, 2),
            'incoming_total' => $incoming,
            'outgoing_total' => $outgoing,
            'net_cash' => round($incoming - $outgoing, 2),
            'average_ticket' => (int) (clone $salesQuery)->count() > 0 ? round($salesAfterDiscount / max((int) (clone $salesQuery)->count(), 1), 2) : 0,
            'method_breakdown' => $methodBreakdown,
            'top_products' => $topProducts,
            'top_returns' => $topReturns,
            'settlement_watch' => $this->settlementWatch($sessions, $payments),
        ];
    }

    private function dailyReportSessionIds(int $companyId, int $branchId, array $filters, string $date): Collection
    {
        $sessionScope = PosSession::query()
            ->select('id')
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->when($filters['warehouse_id'] ?? null, fn (Builder $query, $warehouseId) => $query->where('warehouse_id', $warehouseId))
            ->when($filters['cash_account_id'] ?? null, fn (Builder $query, $cashAccountId) => $query->where('cash_account_id', $cashAccountId));

        $sessionIds = (clone $sessionScope)
            ->where(function (Builder $query) use ($date): void {
                $query
                    ->whereDate('opened_at', $date)
                    ->orWhereDate('closed_at', $date);
            })
            ->pluck('id');

        $activitySessionIds = SalesInvoice::query()
            ->where('company_id', $companyId)
            ->where('sale_channel', 'pos')
            ->whereDate('invoice_date', $date)
            ->whereNotNull('pos_session_id')
            ->pluck('pos_session_id')
            ->merge(
                PosReturn::query()
                    ->where('company_id', $companyId)
                    ->whereDate('return_date', $date)
                    ->whereNotNull('pos_session_id')
                    ->pluck('pos_session_id')
            )
            ->merge(
                Payment::query()
                    ->where('company_id', $companyId)
                    ->whereDate('payment_date', $date)
                    ->whereNotNull('pos_session_id')
                    ->pluck('pos_session_id')
            );

        return $sessionIds
            ->merge($activitySessionIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    private function expectedMethodBreakdown(PosSession $session): array
    {
        $breakdown = $this->zeroMethodBreakdown();
        $breakdown['cash'] = round((float) $session->opening_amount, 2);

        foreach ($session->payments as $payment) {
            if (! array_key_exists($payment->method, $breakdown)) {
                $breakdown[$payment->method] = 0;
            }

            $delta = (float) $payment->amount * ($payment->direction === 'out' ? -1 : 1);
            $breakdown[$payment->method] = round($breakdown[$payment->method] + $delta, 2);
        }

        return $breakdown;
    }

    private function normalizeMethodBreakdown(array $values): array
    {
        $normalized = $this->zeroMethodBreakdown();

        foreach ($this->methodOptions() as $method => $label) {
            $normalized[$method] = round((float) ($values[$method] ?? 0), 2);
        }

        return $normalized;
    }

    private function normalizeVarianceNotes(array $values): array
    {
        $normalized = $this->emptyVarianceNotes();

        foreach ($this->methodOptions() as $method => $label) {
            $normalized[$method] = trim((string) ($values[$method] ?? ''));
        }

        return $normalized;
    }

    private function zeroMethodBreakdown(): array
    {
        return collect($this->methodOptions())
            ->mapWithKeys(fn ($label, $method) => [$method => 0.0])
            ->all();
    }

    private function emptyVarianceNotes(): array
    {
        return collect($this->methodOptions())
            ->mapWithKeys(fn ($label, $method) => [$method => ''])
            ->all();
    }

    private function differenceBreakdown(array $counted, array $expected): array
    {
        $difference = [];

        foreach ($this->methodOptions() as $method => $label) {
            $difference[$method] = round((float) ($counted[$method] ?? 0) - (float) ($expected[$method] ?? 0), 2);
        }

        return $difference;
    }

    private function settlementWatch(EloquentCollection|Collection $sessions, EloquentCollection|Collection $payments): array
    {
        $focusMethods = ['cash', 'wave', 'orange_money', 'moov_money', 'mobile_money'];
        $closedSessions = collect($sessions)->where('status', 'closed')->values();
        $mobileMethods = collect($focusMethods)->reject(fn (string $method) => $method === 'cash')->values();

        $methods = collect($focusMethods)
            ->map(function (string $method) use ($closedSessions, $payments, $mobileMethods): array {
                $methodPayments = collect($payments)->where('method', $method)->values();
                $unreconciledPayments = $methodPayments
                    ->filter(fn (Payment $payment) => ! $payment->reconciliationItem)
                    ->values();
                $expectedTotal = 0.0;
                $countedTotal = 0.0;
                $varianceTotal = 0.0;
                $sessionsWithVariance = 0;

                foreach ($closedSessions as $session) {
                    if (! $session instanceof PosSession) {
                        continue;
                    }

                    $expected = $this->normalizeMethodBreakdown(is_array($session->expected_breakdown) ? $session->expected_breakdown : []);
                    $counted = $this->normalizeMethodBreakdown(is_array($session->counted_breakdown) ? $session->counted_breakdown : []);
                    $variance = $this->normalizeMethodBreakdown(is_array($session->variance_breakdown) ? $session->variance_breakdown : []);
                    $expectedTotal += (float) ($expected[$method] ?? 0);
                    $countedTotal += (float) ($counted[$method] ?? 0);
                    $varianceTotal += (float) ($variance[$method] ?? 0);

                    if (abs((float) ($variance[$method] ?? 0)) > 0.009) {
                        $sessionsWithVariance++;
                    }
                }

                $varianceTotal = round($varianceTotal, 2);
                $unreconciledAmount = round((float) $unreconciledPayments->sum(function (Payment $payment): float {
                    return $payment->direction === 'out'
                        ? -1 * (float) $payment->amount
                        : (float) $payment->amount;
                }), 2);
                $missingReferenceCount = (int) $unreconciledPayments
                    ->filter(fn (Payment $payment) => blank(trim((string) $payment->reference)))
                    ->count();
                $hasMobileExposure = $mobileMethods->contains($method);

                $status = 'ok';
                if (abs($varianceTotal) > 0.009 || abs($unreconciledAmount) > 0.009 || $missingReferenceCount > 0) {
                    $status = (abs($varianceTotal) >= 1000 || abs($unreconciledAmount) >= 1000 || $missingReferenceCount >= 2)
                        ? 'attention'
                        : 'warning';
                }

                return [
                    'method' => $method,
                    'label' => $this->methodOptions()[$method] ?? $method,
                    'expected_total' => round($expectedTotal, 2),
                    'counted_total' => round($countedTotal, 2),
                    'variance' => $varianceTotal,
                    'sessions_with_variance' => $sessionsWithVariance,
                    'payment_count' => (int) $methodPayments->count(),
                    'unreconciled_amount' => $unreconciledAmount,
                    'unreconciled_count' => (int) $unreconciledPayments->count(),
                    'missing_reference_count' => $missingReferenceCount,
                    'status' => $status,
                    'is_mobile' => $hasMobileExposure,
                    'has_activity' => $sessionsWithVariance > 0
                        || abs((float) $expectedTotal) > 0.009
                        || (int) $methodPayments->count() > 0
                        || abs($unreconciledAmount) > 0.009
                        || $missingReferenceCount > 0
                        || $method === 'cash',
                ];
            })
            ->filter(fn (array $row) => $row['has_activity'])
            ->values();
        $cashRow = $methods->firstWhere('method', 'cash');
        $sessionsWithVarianceCount = $closedSessions
            ->filter(function ($session): bool {
                if (! $session instanceof PosSession) {
                    return false;
                }

                $variance = $this->normalizeMethodBreakdown(is_array($session->variance_breakdown) ? $session->variance_breakdown : []);

                return collect($variance)->contains(fn (float $amount) => abs($amount) > 0.009);
            })
            ->count();

        return [
            'closed_sessions_count' => (int) $closedSessions->count(),
            'sessions_with_variance_count' => (int) $sessionsWithVarianceCount,
            'cash_variance_total' => round((float) ($cashRow['variance'] ?? 0), 2),
            'mobile_unreconciled_amount' => round((float) $methods->where('is_mobile', true)->sum('unreconciled_amount'), 2),
            'missing_reference_count' => (int) $methods->where('is_mobile', true)->sum('missing_reference_count'),
            'methods' => $methods->all(),
        ];
    }

    private function allocateGlobalDiscounts(SalesInvoice $invoice): array
    {
        $items = $invoice->items->values();
        $lineDiscountTotal = round((float) $items->sum('discount_total'), 2);
        $globalDiscountTotal = max(round((float) $invoice->discount_total - $lineDiscountTotal, 2), 0);
        $baseAmount = round((float) $items->sum('line_total'), 2);

        if ($globalDiscountTotal <= 0 || $baseAmount <= 0 || $items->isEmpty()) {
            return [];
        }

        $remaining = $globalDiscountTotal;
        $allocations = [];

        foreach ($items as $index => $item) {
            if ($index === $items->count() - 1) {
                $allocated = round($remaining, 2);
            } else {
                $allocated = round($globalDiscountTotal * (((float) $item->line_total) / $baseAmount), 2);
                $remaining = round($remaining - $allocated, 2);
            }

            $allocations[$item->id] = max($allocated, 0);
        }

        return $allocations;
    }

    private function normalizeCashDenominationCounts(array $values): array
    {
        $normalized = [];

        foreach ($this->cashDenominations() as $denomination => $label) {
            $count = (int) ($values[$denomination] ?? 0);
            $normalized[$denomination] = max($count, 0);
        }

        return $normalized;
    }

    private function cashBreakdownTotal(array $breakdown): float
    {
        $total = 0;

        foreach ($this->cashDenominations() as $denomination => $label) {
            $total += ((int) ($breakdown[$denomination] ?? 0)) * (int) $denomination;
        }

        return round((float) $total, 2);
    }

    private function walkInCustomer(int $companyId): Partner
    {
        return Partner::query()->updateOrCreate(
            ['company_id' => $companyId, 'code' => 'CLI-COMPTOIR'],
            [
                'type' => 'customer',
                'name' => 'Client comptoir',
                'phone' => null,
                'email' => null,
                'city' => 'Bamako',
                'address' => 'Vente au comptoir',
                'opening_balance' => 0,
                'notes' => 'Client par defaut pour le point de vente',
                'is_active' => true,
            ]
        );
    }

    private function ensureSequence(int $companyId, string $documentType, string $prefix): void
    {
        DocumentSequence::query()->firstOrCreate(
            [
                'company_id' => $companyId,
                'document_type' => $documentType,
            ],
            [
                'prefix' => $prefix,
                'next_number' => 1,
                'padding' => 5,
            ]
        );
    }
}
