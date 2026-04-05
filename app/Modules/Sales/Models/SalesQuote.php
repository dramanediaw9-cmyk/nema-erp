<?php

namespace App\Modules\Sales\Models;

use App\Models\User;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Company\Models\PriceList;
use App\Modules\Partners\Models\Partner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class SalesQuote extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'customer_id',
        'price_list_id',
        'quote_number',
        'quote_date',
        'valid_until',
        'status',
        'subtotal',
        'total',
        'notes',
        'sent_at',
        'accepted_at',
        'converted_at',
        'converted_sales_invoice_id',
        'converted_sales_order_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quote_date' => 'date',
            'valid_until' => 'date',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'converted_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'customer_id');
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function convertedInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'converted_sales_invoice_id');
    }

    public function convertedOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'converted_sales_order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesQuoteItem::class);
    }

    public function portalActions(): MorphMany
    {
        return $this->morphMany(SalesPortalAction::class, 'actionable');
    }

    public function latestPortalAction(): MorphOne
    {
        return $this->morphOne(SalesPortalAction::class, 'actionable')->latestOfMany('signed_at');
    }

    public function isConvertible(): bool
    {
        return $this->status === 'accepted'
            && ! $this->converted_sales_invoice_id
            && ! $this->converted_sales_order_id;
    }
}
