<?php

namespace App\Modules\Purchases\Models;

use App\Models\User;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Sales\Models\SalesOrder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'warehouse_id',
        'origin_sales_order_id',
        'request_number',
        'request_date',
        'needed_by_date',
        'priority',
        'status',
        'subtotal',
        'total',
        'notes',
        'approved_at',
        'approved_by',
        'rejected_at',
        'rejected_by',
        'converted_at',
        'converted_purchase_order_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'request_date' => 'date',
            'needed_by_date' => 'date',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
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

    public function originSalesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'origin_sales_order_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function convertedPurchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'converted_purchase_order_id');
    }

    public function generatedPurchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'source_purchase_request_id')->latest('id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }
}
