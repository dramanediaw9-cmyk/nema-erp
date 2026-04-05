<?php

namespace App\Modules\Purchases\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Sales\Models\SalesOrderItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_request_id',
        'product_id',
        'origin_sales_order_item_id',
        'description',
        'qty',
        'estimated_unit_cost',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'estimated_unit_cost' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function originSalesOrderItem(): BelongsTo
    {
        return $this->belongsTo(SalesOrderItem::class, 'origin_sales_order_item_id');
    }
}
