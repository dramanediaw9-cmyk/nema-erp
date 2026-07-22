<?php

namespace App\Modules\Core\Imports\Odoo\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OdooProductImportError extends Model
{
    use HasFactory;

    protected $fillable = [
        'odoo_product_import_run_id',
        'odoo_model',
        'odoo_id',
        'phase',
        'message',
        'context',
        'retryable',
    ];

    protected function casts(): array
    {
        return [
            'odoo_id' => 'integer',
            'context' => 'array',
            'retryable' => 'boolean',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(OdooProductImportRun::class, 'odoo_product_import_run_id');
    }
}
