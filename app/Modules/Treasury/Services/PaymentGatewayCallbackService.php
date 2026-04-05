<?php

namespace App\Modules\Treasury\Services;

use App\Models\User;
use App\Modules\Core\Audit\Models\ActivityLog;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Treasury\Models\CashAccount;
use App\Modules\Treasury\Models\PaymentGatewayCallback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class PaymentGatewayCallbackService
{
    public function __construct(
        private readonly PaymentGatewayService $paymentGatewayService,
        private readonly PaymentService $paymentService,
    ) {
    }

    public function handle(Company $company, string $channel, array $payload, Request $request): PaymentGatewayCallback
    {
        $configuration = $this->paymentGatewayService->channelForCompany($company->id, $channel);

        if (! $configuration || ! ($configuration['enabled'] ?? false)) {
            throw ValidationException::withMessages([
                'channel' => 'Ce canal de paiement n est pas actif pour cette societe.',
            ]);
        }

        $configuredSecret = trim((string) ($configuration['callback_secret'] ?? ''));
        $providedSecret = trim((string) ($request->header('X-Nema-Gateway-Secret') ?? data_get($payload, 'secret', '')));

        if ($configuredSecret === '' || ! hash_equals($configuredSecret, $providedSecret)) {
            throw ValidationException::withMessages([
                'secret' => 'Secret de callback invalide pour ce canal.',
            ]);
        }

        $data = $this->validatedPayload($payload, $channel);
        $invoice = SalesInvoice::query()
            ->where('company_id', $company->id)
            ->where('invoice_number', $data['invoice_number'])
            ->first();

        if (! $invoice) {
            throw ValidationException::withMessages([
                'invoice_number' => 'Aucune facture correspondante n a ete trouvee pour cette societe.',
            ]);
        }

        return DB::transaction(function () use ($company, $channel, $configuration, $data, $invoice, $request) {
            $invoice = SalesInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $cashAccount = $this->configuredCashAccount($company->id, $configuration['cash_account_id'] ?? null);

            $callback = PaymentGatewayCallback::query()->firstOrNew([
                'sales_invoice_id' => $invoice->id,
                'channel' => $channel,
                'reference' => $data['reference'],
            ]);

            $callback->fill([
                'tenant_id' => $invoice->tenant_id,
                'company_id' => $invoice->company_id,
                'branch_id' => $invoice->branch_id,
                'payment_id' => $callback->payment_id,
                'cash_account_id' => $cashAccount?->id,
                'gateway_status' => $data['status'],
                'processing_status' => $callback->exists ? $callback->processing_status : 'received',
                'amount' => round((float) $data['amount'], 2),
                'external_reference' => ($data['external_reference'] ?? null) ?: null,
                'payer_name' => ($data['payer_name'] ?? null) ?: null,
                'payer_phone' => ($data['payer_phone'] ?? null) ?: null,
                'paid_at' => ($data['paid_at'] ?? null) ?: null,
                'received_at' => now(),
                'notes' => ($data['notes'] ?? null) ?: null,
                'payload' => [
                    'headers' => [
                        'user_agent' => (string) $request->userAgent(),
                        'x_gateway_signature' => (string) $request->header('X-Gateway-Signature', ''),
                    ],
                    'body' => [
                        'invoice_number' => $data['invoice_number'],
                        'status' => $data['status'],
                        'amount' => round((float) $data['amount'], 2),
                        'reference' => $data['reference'],
                        'external_reference' => ($data['external_reference'] ?? null) ?: null,
                        'paid_at' => ($data['paid_at'] ?? null) ?: null,
                        'payer_name' => ($data['payer_name'] ?? null) ?: null,
                        'payer_phone' => ($data['payer_phone'] ?? null) ?: null,
                        'notes' => ($data['notes'] ?? null) ?: null,
                    ],
                ],
                'error_message' => null,
            ]);
            $callback->save();

            $payment = null;

            if ($data['status'] === 'success' && ! $callback->payment_id) {
                if ((bool) ($configuration['auto_record'] ?? false) && $cashAccount) {
                    try {
                        if ($invoice->status !== 'validated') {
                            throw ValidationException::withMessages([
                                'invoice' => 'La facture n est pas encore completement approuvee.',
                            ]);
                        }

                        if ($invoice->payment_status === 'paid' || (float) $invoice->balance_due <= 0) {
                            $callback->processing_status = 'ignored';
                            $callback->error_message = 'Facture deja totalement reglee au moment du callback.';
                        } elseif ((float) $data['amount'] > (float) $invoice->balance_due) {
                            $callback->processing_status = 'pending_review';
                            $callback->error_message = 'Le montant callback depasse le solde restant et demande une verification manuelle.';
                        } else {
                            $automationUser = $this->resolveAutomationUser($company->id);

                            if (! $automationUser) {
                                $callback->processing_status = 'pending_review';
                                $callback->error_message = 'Aucun utilisateur actif disponible pour enregistrer automatiquement l encaissement.';
                            } else {
                                $payment = $this->paymentService->recordCustomerReceipt(
                                    $company->id,
                                    $invoice->branch_id ?? $cashAccount->branch_id ?? $automationUser->branch_id ?? 0,
                                    $invoice,
                                    $cashAccount,
                                    [
                                        'payment_date' => filled($data['paid_at']) ? $data['paid_at'] : now()->toDateString(),
                                        'amount' => round((float) $data['amount'], 2),
                                        'method' => $channel,
                                        'reference' => $data['reference'],
                                        'notes' => trim(implode(' · ', array_filter([
                                            'Encaissement auto depuis callback '.$this->paymentGatewayService->labelForMethod($channel),
                                            (($data['external_reference'] ?? null) ? 'Ref externe '.($data['external_reference'] ?? null) : null),
                                            (($data['notes'] ?? null) ?: null),
                                        ]))),
                                    ],
                                    $automationUser,
                                );

                                $callback->payment_id = $payment->id;
                                $callback->processing_status = 'auto_recorded';
                                $callback->error_message = null;
                            }
                        }
                    } catch (Throwable $exception) {
                        $callback->processing_status = 'error';
                        $callback->error_message = $exception->getMessage();
                    }
                } else {
                    $callback->processing_status = 'pending_review';
                    $callback->error_message = $cashAccount ? null : 'Aucun compte de tresorerie lie a ce canal pour automatiser l encaissement.';
                }

                $callback->processed_at = now();
                $callback->save();
            } elseif ($data['status'] === 'failed') {
                $callback->processing_status = 'ignored';
                $callback->processed_at = now();
                $callback->save();
            }

            $this->logCallbackActivity($invoice, $callback, $request, $payment?->id);

            return $callback->load(['payment.cashAccount', 'cashAccount']);
        });
    }

    private function validatedPayload(array $payload, string $channel): array
    {
        return Validator::make($payload, [
            'invoice_number' => ['required', 'string', 'max:60'],
            'status' => ['required', Rule::in(['pending', 'success', 'failed'])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference' => ['required', 'string', 'max:120'],
            'external_reference' => ['nullable', 'string', 'max:120'],
            'payer_name' => ['nullable', 'string', 'max:120'],
            'payer_phone' => ['nullable', 'string', 'max:60'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ], [
            'invoice_number.required' => 'Le numero de facture est obligatoire dans le callback.',
            'reference.required' => 'La reference de transaction est obligatoire.',
            'status.in' => 'Le statut du callback doit etre pending, success ou failed.',
        ])->validate();
    }

    private function configuredCashAccount(int $companyId, mixed $cashAccountId): ?CashAccount
    {
        $resolvedId = (int) $cashAccountId;

        if ($resolvedId <= 0) {
            return null;
        }

        return CashAccount::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->find($resolvedId);
    }

    private function resolveAutomationUser(int $companyId): ?User
    {
        $users = User::query()
            ->with('roles')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        foreach (['company_admin', 'director', 'manager', 'operations_officer'] as $roleSlug) {
            $match = $users->first(fn (User $user) => $user->hasRole($roleSlug));

            if ($match) {
                return $match;
            }
        }

        return $users->first();
    }

    private function logCallbackActivity(SalesInvoice $invoice, PaymentGatewayCallback $callback, Request $request, ?int $paymentId): void
    {
        ActivityLog::query()->create([
            'company_id' => $invoice->company_id,
            'branch_id' => $invoice->branch_id,
            'user_id' => null,
            'action' => 'payments.gateway_callback.received',
            'description' => 'Reception callback paiement terrain',
            'subject_type' => $invoice->getMorphClass(),
            'subject_id' => $invoice->getKey(),
            'properties' => [
                'callback_id' => $callback->id,
                'invoice_number' => $invoice->invoice_number,
                'channel' => $callback->channel,
                'gateway_status' => $callback->gateway_status,
                'processing_status' => $callback->processing_status,
                'reference' => $callback->reference,
                'payment_id' => $paymentId,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);
    }
}



