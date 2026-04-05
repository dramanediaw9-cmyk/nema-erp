<?php

namespace App\Modules\Sales\Models;

use App\Models\User;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Company\Models\PriceList;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Partners\Models\Partner;
use App\Modules\Purchases\Models\PurchaseRequest;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class SalesOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'warehouse_id',
        'customer_id',
        'price_list_id',
        'order_number',
        'order_date',
        'requested_delivery_date',
        'customer_reference',
        'source_document',
        'salesperson_name',
        'commitment_date',
        'status',
        'subtotal',
        'total',
        'notes',
        'delivery_instruction',
        'confirmed_at',
        'cancelled_at',
        'converted_at',
        'converted_sales_invoice_id',
        'origin_sales_quote_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'requested_delivery_date' => 'date',
            'commitment_date' => 'date',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
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

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
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

    public function originQuote(): BelongsTo
    {
        return $this->belongsTo(SalesQuote::class, 'origin_sales_quote_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    public function deliveryNotes(): HasMany
    {
        return $this->hasMany(DeliveryNote::class);
    }

    public function generatedPurchaseRequests(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class, 'origin_sales_order_id');
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
        return $this->status === 'confirmed' && ! $this->converted_sales_invoice_id;
    }

    public function hasRemainingDelivery(): bool
    {
        return $this->items->contains(fn (SalesOrderItem $item) => $item->remainingQty() > 0);
    }
}
