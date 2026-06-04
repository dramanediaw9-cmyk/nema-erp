<?php

namespace App\Modules\Treasury\Models;

use App\Modules\Pos\Services\PosSessionLockService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PaymentAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'allocatable_type',
        'allocatable_id',
        'allocated_amount',
    ];

    protected function casts(): array
    {
        return [
            'allocated_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (PaymentAllocation $allocation): void {
            app(PosSessionLockService::class)->assertPaymentAllocationEditable($allocation);
        });

        static::deleting(function (PaymentAllocation $allocation): void {
            app(PosSessionLockService::class)->assertPaymentAllocationEditable($allocation);
        });
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function allocatable(): MorphTo
    {
        return $this->morphTo();
    }
}
