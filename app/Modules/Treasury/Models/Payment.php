<?php

namespace App\Modules\Treasury\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasDocumentCollaboration;
use App\Models\User;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Partners\Models\Partner;
use App\Modules\Pos\Models\PosSession;
use App\Modules\Pos\Services\PosSessionLockService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    use BelongsToTenant;
    use HasDocumentCollaboration;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'cash_account_id',
        'pos_session_id',
        'partner_id',
        'payment_number',
        'direction',
        'payment_type',
        'payment_date',
        'amount',
        'method',
        'reference',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $payment): void {
            app(PosSessionLockService::class)->assertPaymentEditable($payment);
        });

        static::updating(function (Payment $payment): void {
            if ($payment->isDirty(['cash_account_id', 'payment_date', 'amount', 'method', 'reference', 'notes'])) {
                app(PosSessionLockService::class)->assertPaymentEditable($payment);
            }
        });

        static::deleting(function (Payment $payment): void {
            app(PosSessionLockService::class)->assertPaymentEditable($payment);
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function posSession(): BelongsTo
    {
        return $this->belongsTo(PosSession::class, 'pos_session_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function reconciliationItem(): HasOne
    {
        return $this->hasOne(TreasuryReconciliationPayment::class);
    }
}
