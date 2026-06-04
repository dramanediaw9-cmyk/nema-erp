<?php

namespace App\Modules\Pos\Services;

use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Pos\Models\PosReturn;
use App\Modules\Pos\Models\PosSession;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesInvoiceItem;
use App\Modules\Treasury\Models\Payment;
use App\Modules\Treasury\Models\PaymentAllocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class PosSessionLockService
{
    public function assertInvoiceEditable(SalesInvoice $invoice, string $operation = 'modifier ce ticket POS'): void
    {
        if ($this->invoiceIsLocked($invoice)) {
            throw ValidationException::withMessages([
                'sale' => 'Caisse fermee : impossible de '.$operation.'.',
            ]);
        }
    }

    public function assertInvoiceItemEditable(SalesInvoiceItem $item, string $operation = 'modifier une ligne de ce ticket POS'): void
    {
        $item->loadMissing('invoice.posSession');

        if ($item->invoice instanceof SalesInvoice) {
            $this->assertInvoiceEditable($item->invoice, $operation);
        }
    }

    public function assertPaymentEditable(Payment $payment, string $operation = 'changer ce paiement'): void
    {
        if ($this->paymentIsLocked($payment)) {
            throw ValidationException::withMessages([
                'payment' => 'Caisse fermee : impossible de '.$operation.'.',
            ]);
        }
    }

    public function assertPaymentAllocationEditable(PaymentAllocation $allocation, string $operation = 'changer ce paiement'): void
    {
        $allocation->loadMissing(['payment.posSession', 'allocatable']);

        if ($allocation->payment instanceof Payment) {
            $this->assertPaymentEditable($allocation->payment, $operation);
        }

        if ($allocation->allocatable instanceof SalesInvoice) {
            $this->assertInvoiceEditable($allocation->allocatable, $operation);
        }
    }

    public function assertStockMovementEditable(StockMovement $movement, string $operation = 'modifier le stock lie'): void
    {
        if ($this->stockMovementIsLocked($movement)) {
            throw ValidationException::withMessages([
                'stock' => 'Caisse fermee : impossible de '.$operation.'.',
            ]);
        }
    }

    public function invoiceIsLocked(SalesInvoice $invoice): bool
    {
        if (! $invoice->pos_session_id) {
            return false;
        }

        $session = $invoice->relationLoaded('posSession')
            ? $invoice->posSession
            : PosSession::query()->find($invoice->pos_session_id);

        return $session instanceof PosSession && ! $session->isOpen();
    }

    public function paymentIsLocked(Payment $payment): bool
    {
        if ($payment->pos_session_id) {
            $session = $payment->relationLoaded('posSession')
                ? $payment->posSession
                : PosSession::query()->find($payment->pos_session_id);

            if ($session instanceof PosSession && ! $session->isOpen()) {
                return true;
            }
        }

        $payment->loadMissing('allocations.allocatable');

        return $payment->allocations
            ->pluck('allocatable')
            ->filter(fn ($allocatable) => $allocatable instanceof SalesInvoice)
            ->contains(fn (SalesInvoice $invoice) => $this->invoiceIsLocked($invoice));
    }

    public function stockMovementIsLocked(StockMovement $movement): bool
    {
        if ($movement->reference_type === SalesInvoice::class && $movement->reference_id) {
            $invoice = SalesInvoice::query()->find($movement->reference_id);

            return $invoice instanceof SalesInvoice && $this->invoiceIsLocked($invoice);
        }

        if ($movement->reference_type === PosReturn::class && $movement->reference_id) {
            $return = PosReturn::query()->with('session')->find($movement->reference_id);

            return $return instanceof PosReturn && $return->session instanceof PosSession && ! $return->session->isOpen();
        }

        $reference = $movement->relationLoaded('reference') ? $movement->reference : null;

        return $reference instanceof Model
            && method_exists($reference, 'session')
            && $reference->session instanceof PosSession
            && ! $reference->session->isOpen();
    }
}
