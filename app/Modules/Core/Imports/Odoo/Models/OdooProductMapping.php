<?php

namespace App\Modules\Core\Imports\Odoo\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OdooProductMapping extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'odoo_connection_id',
        'product_id',
        'odoo_model',
        'odoo_id',
        'odoo_template_id',
        'source_hash',
        'odoo_write_date',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'odoo_id' => 'integer',
            'odoo_template_id' => 'integer',
            'odoo_write_date' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(OdooConnection::class, 'odoo_connection_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
