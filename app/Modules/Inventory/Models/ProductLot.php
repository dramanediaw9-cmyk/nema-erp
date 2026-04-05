<?php

namespace App\Modules\Inventory\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Purchases\Models\GoodsReceipt;
use App\Modules\Purchases\Models\GoodsReceiptItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class ProductLot extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'warehouse_id',
        'product_id',
        'goods_receipt_id',
        'goods_receipt_item_id',
        'tracking_type',
        'lot_number',
        'serial_number',
        'expires_at',
        'received_at',
        'unit_cost',
        'quantity_received',
        'quantity_available',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'date',
            'received_at' => 'date',
            'unit_cost' => 'decimal:2',
            'quantity_received' => 'decimal:3',
            'quantity_available' => 'decimal:3',
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function goodsReceiptItem(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function displayCode(): string
    {
        return (string) ($this->serial_number ?: $this->lot_number ?: 'Sans reference');
    }

    public function isExpired(): bool
    {
        return $this->expires_at instanceof Carbon && $this->expires_at->isPast();
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        if (! $this->expires_at instanceof Carbon || $this->isExpired()) {
            return false;
        }

        return $this->expires_at->lte(now()->addDays($days)->endOfDay());
    }

    public function expiryStatus(int $days = 30): string
    {
        if (! $this->expires_at instanceof Carbon) {
            return 'no_expiry';
        }

        if ($this->isExpired()) {
            return 'expired';
        }

        if ($this->isExpiringSoon($days)) {
            return 'expiring';
        }

        return 'healthy';
    }
}
