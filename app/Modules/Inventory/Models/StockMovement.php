<?php

namespace App\Modules\Inventory\Models;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Pos\Services\PosSessionLockService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'warehouse_id',
        'product_id',
        'product_lot_id',
        'movement_type',
        'quantity_in',
        'quantity_out',
        'unit_cost',
        'reference_type',
        'reference_id',
        'reason',
        'notes',
        'movement_date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity_in' => 'decimal:3',
            'quantity_out' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'movement_date' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (StockMovement $movement): void {
            app(PosSessionLockService::class)->assertStockMovementEditable($movement);
        });

        static::updating(function (StockMovement $movement): void {
            app(PosSessionLockService::class)->assertStockMovementEditable($movement);
        });

        static::deleting(function (StockMovement $movement): void {
            app(PosSessionLockService::class)->assertStockMovementEditable($movement);
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

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productLot(): BelongsTo
    {
        return $this->belongsTo(ProductLot::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id');
    }
}


