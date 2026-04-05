<?php

namespace App\Modules\Treasury\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Sales\Models\SalesInvoice;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentGatewayCallback extends Model
{
    use HasFactory;
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'sales_invoice_id',
        'payment_id',
        'cash_account_id',
        'channel',
        'gateway_status',
        'processing_status',
        'amount',
        'reference',
        'external_reference',
        'payer_name',
        'payer_phone',
        'paid_at',
        'received_at',
        'processed_at',
        'notes',
        'payload',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function channelLabel(): string
    {
        return match ($this->channel) {
            'wave' => 'Wave',
            'orange_money' => 'Orange Money',
            'moov_money' => 'Moov Money',
            'bank_transfer' => 'Virement bancaire',
            default => ucfirst(str_replace('_', ' ', $this->channel)),
        };
    }

    public function gatewayStatusLabel(): string
    {
        return match ($this->gateway_status) {
            'success' => 'Succes',
            'failed' => 'Echec',
            default => 'En attente',
        };
    }

    public function processingStatusLabel(): string
    {
        return match ($this->processing_status) {
            'auto_recorded' => 'Encaissement auto enregistre',
            'pending_review' => 'A rapprocher',
            'ignored' => 'Ignore',
            'rejected' => 'Rejete',
            'error' => 'Erreur de traitement',
            default => 'Recu',
        };
    }
}
