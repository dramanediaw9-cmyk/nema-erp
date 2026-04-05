<?php

namespace App\Modules\Catalog\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Modules\Partners\Models\Partner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSupplier extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'product_id',
        'supplier_id',
        'supplier_product_code',
        'supplier_product_name',
        'min_qty',
        'unit_cost',
        'lead_time_days',
        'is_preferred',
    ];

    protected function casts(): array
    {
        return [
            'min_qty' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'lead_time_days' => 'integer',
            'is_preferred' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'supplier_id');
    }
}