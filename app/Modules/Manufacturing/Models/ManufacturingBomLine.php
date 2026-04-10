<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManufacturingBomLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'manufacturing_bom_id',
        'component_code',
        'component_name',
        'quantity',
        'unit',
        'wastage_rate',
        'notes',
        'sequence',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'wastage_rate' => 'decimal:2',
            'sequence' => 'integer',
        ];
    }

    public function billOfMaterial(): BelongsTo
    {
        return $this->belongsTo(ManufacturingBom::class, 'manufacturing_bom_id');
    }
}
