<?php

namespace App\Modules\Collections\Models;

use App\Models\User;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Partners\Models\Partner;
use App\Modules\Sales\Models\SalesInvoice;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionFollowUp extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'sales_invoice_id',
        'customer_id',
        'action_date',
        'action_type',
        'outcome',
        'contact_name',
        'contact_phone',
        'promised_amount',
        'promised_date',
        'next_action_date',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'action_date' => 'date',
            'promised_date' => 'date',
            'next_action_date' => 'date',
            'promised_amount' => 'decimal:2',
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'customer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}