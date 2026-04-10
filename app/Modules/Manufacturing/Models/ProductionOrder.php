<?php

namespace App\Modules\Manufacturing\Models;

use App\Models\User;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'order_number',
        'reference',
        'bill_of_material_id',
        'item_name',
        'planned_quantity',
        'completed_quantity',
        'material_cost_estimate',
        'actual_material_cost',
        'planned_start_date',
        'due_date',
        'status',
        'routing_stage',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'planned_quantity' => 'decimal:3',
            'completed_quantity' => 'decimal:3',
            'material_cost_estimate' => 'decimal:2',
            'actual_material_cost' => 'decimal:2',
            'planned_start_date' => 'date',
            'due_date' => 'date',
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

    public function billOfMaterial(): BelongsTo
    {
        return $this->belongsTo(ManufacturingBom::class, 'bill_of_material_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
