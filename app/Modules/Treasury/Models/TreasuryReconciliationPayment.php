<?php

namespace App\Modules\Treasury\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreasuryReconciliationPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'treasury_reconciliation_id',
        'payment_id',
    ];

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(TreasuryReconciliation::class, 'treasury_reconciliation_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
